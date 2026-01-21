<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Runtime;

use Upside\Tpl\Core\AST\{Node, SequenceNode};
use Upside\Tpl\Core\Compiler\{Compiler, CompileRegistry, PhpEmitter};
use Upside\Tpl\Core\Diagnostics\TplException;
use Upside\Tpl\Core\Lexer\{Lexer, LexerRule, LexerState, RuleSet, Source, Token, TokenStream};
use Upside\Tpl\Core\Optimizer\{OptimizeContext, OptimizeException, OptimizerPass, OptimizerRegistry};
use Upside\Tpl\Core\Parser\{Parser, ParserRegistry, StatementParser};

/* -------------------------------------------------------------------------
 *  Engine (ничего не подключает сам)
 * -------------------------------------------------------------------------
 *  Ты собираешь его снаружи:
 *    - добавляешь LexerRule / StatementParser / Compilers / OptimizerPass
 *    - передаёшь shared зависимости через setShared()
 */

final class Engine
{
    private readonly RuleSet $lexerRules;
    private readonly ParserRegistry $stmtParsers;
    private readonly CompileRegistry $nodeCompilers;
    private readonly CompileRegistry $exprCompilers;
    private readonly OptimizerRegistry $optimizers;

    /** @var list<callable(PhpEmitter):void> */
    private array $compilePrologues = [];

    /** @var array<string, array> */
    private array $optimizerOptions = [];

    /** @var array<string, mixed> */
    private array $shared = [];

    /** @var array<string, \Closure> */
    private array $memoryCache = [];

    private readonly FileTemplateCache $fileCache;

    /** строка, чтобы инвалидировать кэш при смене состава сборки */
    private string $cacheSalt = 'core-v1';
    private int $configVersion = 0;

    /** 0 = без лимита */
    private int $memoryCacheLimit = 0;

    public function __construct(
        string $cacheDir,
        public readonly bool $debug = false
    )
    {
        $this->lexerRules = new RuleSet();
        $this->stmtParsers = new ParserRegistry();
        $this->nodeCompilers = new CompileRegistry();
        $this->exprCompilers = new CompileRegistry();
        $this->optimizers = new OptimizerRegistry();
        $this->fileCache = new FileTemplateCache($cacheDir);
    }

    /* -------- сборка -------- */

    public function addCacheSalt(string $part): void
    {
        $this->cacheSalt .= '|' . $part;
    }

    public function addLexerRule(string $mode, LexerRule $rule, int $priority = 0): void
    {
        $this->lexerRules->add($mode, $rule, $priority);
        $this->bumpConfig();
    }

    public function addStatementParser(StatementParser $p, int $priority = 0): void
    {
        $this->stmtParsers->add($p, $priority);
        $this->bumpConfig();
    }

    public function addNodeCompiler(string $class, callable $fn): void
    {
        $this->nodeCompilers->add($class, $fn);
        $this->bumpConfig();
    }

    public function addExprCompiler(string $class, callable $fn): void
    {
        $this->exprCompilers->add($class, $fn);
        $this->bumpConfig();
    }

    public function addCompilePrologue(callable $fn): void
    {
        $this->compilePrologues[] = $fn;
        $this->bumpConfig();
    }

    public function addOptimizer(OptimizerPass $pass, int $priority = 0): void
    {
        $this->optimizers->add($pass, $priority);
    }

    public function setOptimizerOptions(string $id, array $options): void
    {
        $this->optimizerOptions[$id] = $options;
    }

    public function optimizerOptions(string $id): array
    {
        return $this->optimizerOptions[$id] ?? [];
    }

    /* -------- shared зависимости (передаются снаружи) -------- */

    public function setShared(string $id, mixed $value): void
    {
        $this->shared[$id] = $value;
    }

    public function hasShared(string $id): bool
    {
        return array_key_exists($id, $this->shared);
    }

    public function shared(string $id): mixed
    {
        if (!array_key_exists($id, $this->shared)) {
            throw new \RuntimeException("Shared dependency not found: {$id}");
        }
        return $this->shared[$id];
    }

    public function withShared(string $id, mixed $value, callable $fn): mixed
    {
        $had = array_key_exists($id, $this->shared);
        $prev = $had ? $this->shared[$id] : null;
        $this->shared[$id] = $value;

        try {
            return $fn();
        } finally {
            if ($had) {
                $this->shared[$id] = $prev;
            } else {
                unset($this->shared[$id]);
            }
        }
    }

    public function setMemoryCacheLimit(int $max): void
    {
        $this->memoryCacheLimit = max(0, $max);
    }

    public function clearMemoryCache(): void
    {
        $this->memoryCache = [];
    }

    /* -------- оптимизация -------- */

    private function optimizerSignature(): string
    {
        return $this->optimizers->signature($this->optimizerOptions);
    }

    private function optimizeAst(Node $ast, Source $src): Node
    {
        foreach ($this->optimizers->allSorted() as $pass) {
            try {
                $ctx = new OptimizeContext($this, $src, $pass->id());
                $ast = $pass->optimize($ast, $ctx);
            } catch (TplException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw new OptimizeException("Optimizer {$pass->id()} failed: " . $e->getMessage(), $src, null, $e);
            }
        }
        return $ast;
    }

    /* -------- render -------- */

    public function render(string|Source $template, array $context = []): string
    {
        $src = is_string($template) ? new Source('inline', $template) : $template;

        // render может быть вложенным (include), поэтому сохраняем текущий source
        $prevSource = $this->shared['core.source'] ?? null;
        $this->setShared('core.source', $src);

        try {
            $key = hash('xxh3', $src->code . '|' . $this->cacheSalt . '|' . $this->configVersion . '|' . $this->optimizerSignature());

            $fn = $this->memoryCache[$key] ?? null;
            if (!$fn) {
                $fn = $this->fileCache->load($key);

                if (!$fn) {
                    // 1) lex
                    $state = new LexerState($src, $this);
                    $tokens = (new Lexer($state, $this->lexerRules))->tokenize();

                    // 2) parse
                    $ts = new TokenStream($tokens, $src);
                    $ast = (new Parser($this->stmtParsers))->parse($ts, $this);

                    // 3) optimize
                    $ast = $this->optimizeAst($ast, $src);

                    // 4) compile -> php
                    $compiler = new Compiler($this->nodeCompilers, $this->exprCompilers, $this->compilePrologues);
                    $php = $compiler->compileToPhp($src, $ast, $this);

                    // 5) store & load closure
                    $fn = $this->fileCache->store($key, $php);
                }

                $this->memoryCache[$key] = $fn;
                $this->enforceMemoryCacheLimit($key);
            }

            return $fn($context, $this);
        } catch (TplException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TplException("Unhandled error: " . $e->getMessage(), $src, null, $e);
        } finally {
            if ($prevSource !== null) $this->setShared('core.source', $prevSource);
        }
    }

    private function bumpConfig(): void
    {
        $this->configVersion++;
    }

    private function enforceMemoryCacheLimit(string $lastKey): void
    {
        if ($this->memoryCacheLimit <= 0) return;
        while (count($this->memoryCache) > $this->memoryCacheLimit) {
            $dropKey = array_key_first($this->memoryCache);
            if ($dropKey === null || $dropKey === $lastKey) {
                break;
            }
            unset($this->memoryCache[$dropKey]);
        }
    }

    /* -------- debug utilities -------- */

    /** @return list<Token> */
    public function debugTokens(string|Source $template): array
    {
        $src = is_string($template) ? new Source('inline', $template) : $template;
        $state = new LexerState($src, $this);
        return (new Lexer($state, $this->lexerRules))->tokenize();
    }

    public function debugAst(string|Source $template): SequenceNode
    {
        $src = is_string($template) ? new Source('inline', $template) : $template;
        $tokens = $this->debugTokens($src);
        $ts = new TokenStream($tokens, $src);
        return (new Parser($this->stmtParsers))->parse($ts, $this);
    }

    public function debugOptimizedAst(string|Source $template): Node
    {
        $src = is_string($template) ? new Source('inline', $template) : $template;
        $ast = $this->debugAst($src);
        return $this->optimizeAst($ast, $src);
    }

    public function debugDumpAst(string|Source $template, bool $optimized = false): string
    {
        $ast = $optimized ? $this->debugOptimizedAst($template) : $this->debugAst($template);
        return AstDumper::dump($ast) . "\n";
    }

    public function debugCompiledPhp(string|Source $template): string
    {
        $src = is_string($template) ? new Source('inline', $template) : $template;

        $tokens = $this->debugTokens($src);
        $ts = new TokenStream($tokens, $src);
        $ast = (new Parser($this->stmtParsers))->parse($ts, $this);
        $ast = $this->optimizeAst($ast, $src);

        $compiler = new Compiler($this->nodeCompilers, $this->exprCompilers, $this->compilePrologues);
        return $compiler->compileToPhp($src, $ast, $this);
    }
}
