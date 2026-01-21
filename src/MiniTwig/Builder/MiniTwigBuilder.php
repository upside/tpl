<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Builder;

use Upside\Tpl\Core\Compiler\{CompileContext, PhpEmitter};
use Upside\Tpl\Core\Optimizer\OptimizerPass;
use Upside\Tpl\Core\Runtime\{Engine, TemplateLoader};
use Upside\Tpl\MiniTwig\Expr\{BinaryExpr, CallExpr, ExprParser, FilterExpr, GetAttrExpr, LitExpr, NameExpr, OperatorTable, ParentExpr, UnaryExpr};
use Upside\Tpl\MiniTwig\Lexer\{CodeRule, DataRule, Syntax};
use Upside\Tpl\MiniTwig\Nodes\{AssertNode, BlockNode, DebugNode, ExtendsNode, ForNode, IfNode, IncludeNode, PrintNode, RepeatNode, SwitchNode, TextNode};
use Upside\Tpl\MiniTwig\Optimizer\{InlineIncludePass, MergeTextNodesPass};
use Upside\Tpl\MiniTwig\Runtime\Runtime;
use Upside\Tpl\MiniTwig\Tags\{AssertTag, BlockTag, DebugTag, ExtendsStmtParser, ForTag, IfTag, IncludeTag, PrintStmtParser, RepeatTag, SwitchTag, TagRegistry, TagStmtParser, TextStmtParser, UnlessTag};

/* -------------------------------------------------------------------------
 *  MiniTwigBuilder — "внешняя" сборка языка (ядро остаётся чистым)
 * -------------------------------------------------------------------------
 *  Ты сам решаешь какие фичи включать:
 *    - enableIf()
 *    - enableFor()
 *    - enableInclude()
 *    - addOptimizer(...)
 *
 *  И сам передаёшь зависимости:
 *    - Syntax в правила лексера
 *    - Runtime кладёшь в Engine->shared('mt.rt')
 *    - Loader кладёшь в Engine->shared('tpl.loader') (если нужен include)
 */
final class MiniTwigBuilder
{
    private readonly TagRegistry $tags;
    private readonly ExprParser $expr;

    private bool $prologueRtAdded = false;
    private bool $prologueLoaderAdded = false;

    public function __construct(
        private readonly Engine $engine,
        private readonly Syntax $syntax,
        private readonly Runtime $runtime,
        ?OperatorTable $ops = null
    ) {
        $this->tags = new TagRegistry();

        $ops ??= self::defaultOps();
        $this->expr = new ExprParser($ops);
    }

    /** Быстрая сборка стандартной таблицы операторов (можешь передать свою) */
        public static function defaultOps(): OperatorTable {
            $ops = new OperatorTable();
            $ops->prefix('not', 70, '!');
            $ops->prefix('-', 70, '-');

            $ops->binary('or', 10, '||');
            $ops->binary('and', 20, '&&');
            $ops->binary('|', 5, '|');
            foreach (['==','!=','<','<=','>','>='] as $op) $ops->binary($op, 30, $op);
            $ops->binary('~', 40, '.');
        foreach (['+','-'] as $op) $ops->binary($op, 50, $op);
        foreach (['*','/','%'] as $op) $ops->binary($op, 60, $op);

        return $ops;
    }

    /** Регистрирует базовые вещи языка: лексер/парсеры/expr-компиляция/print/text */
    public function registerBase(): self
    {
        // runtime доступен в compiled templates через shared
        $this->engine->setShared('mt.rt', $this->runtime);

        // lexer rules
        $this->engine->addLexerRule('DATA', new DataRule($this->syntax), 0);
        $this->engine->addLexerRule('VAR',  new CodeRule($this->syntax), 0);
        $this->engine->addLexerRule('TAG',  new CodeRule($this->syntax), 0);

        // statement parsers
        $this->engine->addStatementParser(new TextStmtParser(), 100);
        $this->engine->addStatementParser(new PrintStmtParser($this->expr), 90);
        $this->engine->addStatementParser(new TagStmtParser($this->tags), 80);

        // prologue: $rt
        if (!$this->prologueRtAdded) {
            $this->engine->addCompilePrologue(function(PhpEmitter $out) {
                $out->wl('$rt = $env->shared(\'mt.rt\');');
            });
            $this->prologueRtAdded = true;
        }

        // node compilers
        $this->engine->addNodeCompiler(TextNode::class, function(TextNode $n, CompileContext $c) {
            if ($n->text === '') return;
            $c->out->wl('$__out .= ' . var_export($n->text, true) . ';');
        });

        $this->engine->addNodeCompiler(PrintNode::class, function(PrintNode $n, CompileContext $c) {
            $c->out->w(str_repeat('    ', 1) . '$__out .= $rt->escape(');
            $c->expr($n->expr);
            $c->out->w(");\n");
        });

        // expr compilers
        $this->engine->addExprCompiler(LitExpr::class, fn(LitExpr $x, CompileContext $c) => $c->out->w(var_export($x->v, true)));

        $this->engine->addExprCompiler(NameExpr::class, function(NameExpr $x, CompileContext $c) {
            $c->out->w("\$rt->get(\$context, " . var_export($x->name, true) . ")");
        });

        $this->engine->addExprCompiler(GetAttrExpr::class, function(GetAttrExpr $x, CompileContext $c) {
            $c->out->w("\$rt->getAttr(");
            $c->expr($x->base);
            $c->out->w(", " . var_export($x->attr, true) . ")");
        });

            $this->engine->addExprCompiler(UnaryExpr::class, function(UnaryExpr $x, CompileContext $c) {
                // Pratt-table преобразовали в php-оператор на этапе компиляции не храним,
                // поэтому просто маппим минимально:
                $php = match ($x->op) {
                    'not' => '!',
                    '-' => '-',
                    default => throw new \RuntimeException("Unknown unary op {$x->op}")
                };
                $c->out->w($php . '(');
                $c->expr($x->e);
                $c->out->w(')');
            });

            $this->engine->addExprCompiler(ParentExpr::class, function(ParentExpr $x, CompileContext $c) {
                $c->out->w("(\$env->shared('mt.parent'))(\$context, \$env)");
            });

            $this->engine->addExprCompiler(CallExpr::class, function(CallExpr $x, CompileContext $c) {
                if (!$x->callee instanceof NameExpr) {
                    throw new \RuntimeException('Only simple function calls are supported.');
                }
                $c->out->w("\$rt->callFunction(" . var_export($x->callee->name, true) . ", [");
                $first = true;
                foreach ($x->args as $arg) {
                    if (!$first) $c->out->w(', ');
                    $c->expr($arg);
                    $first = false;
                }
                $c->out->w('])');
            });

            $this->engine->addExprCompiler(FilterExpr::class, function(FilterExpr $x, CompileContext $c) {
                $c->out->w("\$rt->applyFilter(" . var_export($x->name, true) . ", ");
                $c->expr($x->input);
                $c->out->w(', [');
                $first = true;
                foreach ($x->args as $arg) {
                    if (!$first) $c->out->w(', ');
                    $c->expr($arg);
                    $first = false;
                }
                $c->out->w('])');
            });

            $this->engine->addExprCompiler(BinaryExpr::class, function(BinaryExpr $x, CompileContext $c) {
                $php = match ($x->op) {
                    'and' => '&&',
                    'or'  => '||',
                '~'   => '.',
                default => $x->op, // + - * / % == != < <= > >=
            };
            $c->out->w('(');
            $c->expr($x->l);
            $c->out->w(' ' . $php . ' ');
            $c->expr($x->r);
            $c->out->w(')');
        });

        return $this;
    }

    public function enableIf(): self
    {
        $this->tags->add(new IfTag($this->expr));

        $this->engine->addNodeCompiler(IfNode::class, function(IfNode $n, CompileContext $c) {
            $first = true;
            foreach ($n->branches as $br) {
                $c->out->w(str_repeat('    ', 1) . ($first ? 'if (' : 'elseif ('));
                $c->expr($br['cond']);
                $c->out->w(") {\n");
                $c->node($br['body']);
                $c->out->wl(str_repeat('    ', 1) . '}');
                $first = false;
            }
            if ($n->elseBody) {
                $c->out->wl(str_repeat('    ', 1) . 'else {');
                $c->node($n->elseBody);
                $c->out->wl(str_repeat('    ', 1) . '}');
            }
        });

        return $this;
    }

    public function enableFor(): self
    {
        $this->tags->add(new ForTag($this->expr));

        $this->engine->addNodeCompiler(ForNode::class, function(ForNode $n, CompileContext $c) {
            $varKey = var_export($n->varName, true);

            $c->out->wl(str_repeat('    ', 1) . '$__parent = $context;');
            $c->out->w(str_repeat('    ', 1) . '$__iter = $rt->toIterable(');
            $c->expr($n->iterable);
            $c->out->w(");\n");

            $c->out->wl(str_repeat('    ', 1) . '$__hasAny = false;');
            $c->out->wl(str_repeat('    ', 1) . 'foreach ($__iter as $__val) {');
            $c->out->wl(str_repeat('    ', 2) . '$__hasAny = true;');
            $c->out->wl(str_repeat('    ', 2) . '$context = $__parent;');
            $c->out->wl(str_repeat('    ', 2) . "\$context[{$varKey}] = \$__val;");

            $c->node($n->body);

            $c->out->wl(str_repeat('    ', 1) . '}');
            $c->out->wl(str_repeat('    ', 1) . '$context = $__parent;');

            if ($n->elseBody) {
                $c->out->wl(str_repeat('    ', 1) . 'if (!$__hasAny) {');
                $c->node($n->elseBody);
                $c->out->wl(str_repeat('    ', 1) . '}');
            }
        });

        return $this;
    }

        public function enableInclude(): self
        {
            $this->tags->add(new IncludeTag($this->expr));

        if (!$this->prologueLoaderAdded) {
            $this->engine->addCompilePrologue(function(PhpEmitter $out) {
                $out->wl('$loader = $env->shared(\'tpl.loader\');');
            });
            $this->prologueLoaderAdded = true;
        }

        $this->engine->addNodeCompiler(IncludeNode::class, function(IncludeNode $n, CompileContext $c) {
            $c->out->w(str_repeat('    ', 1) . '$__out .= $env->render($loader->load((string)(');
            $c->expr($n->templateExpr);
            $c->out->w(")), \$context);\n");
        });

            return $this;
        }

        public function enableInheritance(): self
        {
            $this->tags->add(new BlockTag());

            // parser for {% extends ... %} must run before Text/Tag parsers
            $this->engine->addStatementParser(new ExtendsStmtParser($this->expr), 110);

            if (!$this->prologueLoaderAdded) {
                $this->engine->addCompilePrologue(function(PhpEmitter $out) {
                    $out->wl('$loader = $env->shared(\'tpl.loader\');');
                });
                $this->prologueLoaderAdded = true;
            }

            $this->engine->addNodeCompiler(BlockNode::class, function(BlockNode $n, CompileContext $c) {
                $name = var_export($n->name, true);
                $c->out->wl(str_repeat('    ', 1) . '$__block = null;');
                $c->out->wl(str_repeat('    ', 1) . 'if ($env->hasShared(\'mt.blocks\')) {');
                $c->out->wl(str_repeat('    ', 2) . "\$__block = \$env->shared('mt.blocks')[{$name}] ?? null;");
                $c->out->wl(str_repeat('    ', 1) . '}');
                $c->out->wl(str_repeat('    ', 1) . '$__parent = static function(array $context, \\Upside\\Tpl\\Core\\Runtime\\Engine $env): string {');
                $c->out->ind(2);
                $c->out->wl('$__out = \'\';');
                $c->out->wl('$rt = $env->shared(\'mt.rt\');');
                $c->node($n->body);
                $c->out->wl('return $__out;');
                $c->out->ind(-2);
                $c->out->wl(str_repeat('    ', 1) . '};');
                $c->out->wl(str_repeat('    ', 1) . 'if ($__block) {');
                $c->out->wl(str_repeat('    ', 2) . '$__out .= $env->withShared(\'mt.parent\', $__parent, function() use ($context, $env, $__block) { return $__block($context, $env); });');
                $c->out->wl(str_repeat('    ', 1) . '} else {');
                $c->out->wl(str_repeat('    ', 2) . '$__out .= $__parent($context, $env);');
                $c->out->wl(str_repeat('    ', 1) . '}');
            });

            $this->engine->addNodeCompiler(ExtendsNode::class, function(ExtendsNode $n, CompileContext $c) {
                $c->out->wl(str_repeat('    ', 1) . '$__blocks = $env->hasShared(\'mt.blocks\') ? $env->shared(\'mt.blocks\') : [];');

                foreach ($n->blocks as $b) {
                    $name = var_export($b->name, true);
                    $c->out->wl(str_repeat('    ', 1) . "\$__blocks[{$name}] = static function(array \$context, \\Upside\\Tpl\\Core\\Runtime\\Engine \$env): string {");
                    $c->out->ind(2);
                    $c->out->wl('$__out = \'\';');
                    $c->out->wl('$rt = $env->shared(\'mt.rt\');');
                    $c->node($b->body);
                    $c->out->wl('return $__out;');
                    $c->out->ind(-2);
                    $c->out->wl(str_repeat('    ', 1) . '};');
                }

                $c->out->w(str_repeat('    ', 1) . '$__out .= $env->withShared(\'mt.blocks\', $__blocks, function() use ($context, $env, $loader, $rt) { return $env->render($loader->load((string)(');
                $c->expr($n->templateExpr);
                $c->out->w(")), \$context); });\n");
            });

            return $this;
        }

        public function enableUnless(): self
        {
            $this->tags->add(new UnlessTag($this->expr));
            return $this;
        }

        public function enableAssertDebug(): self
        {
            $this->tags->add(new AssertTag($this->expr));
            $this->tags->add(new DebugTag($this->expr));

            $this->engine->addNodeCompiler(AssertNode::class, function(AssertNode $n, CompileContext $c) {
                $c->out->wl(str_repeat('    ', 1) . 'if ($env->debug) {');
                $c->out->w(str_repeat('    ', 2) . '$rt->assert(');
                $c->expr($n->cond);
                $c->out->w(', ');
                if ($n->message) {
                    $c->expr($n->message);
                } else {
                    $c->out->w("''");
                }
                $c->out->w(");\n");
                $c->out->wl(str_repeat('    ', 1) . '}');
            });

            $this->engine->addNodeCompiler(DebugNode::class, function(DebugNode $n, CompileContext $c) {
                $c->out->wl(str_repeat('    ', 1) . 'if ($env->debug) {');
                $c->out->w(str_repeat('    ', 2) . '$__out .= $rt->debug(');
                $c->expr($n->expr);
                $c->out->w(");\n");
                $c->out->wl(str_repeat('    ', 1) . '}');
            });

            return $this;
        }

        public function enableRepeat(): self
        {
            $this->tags->add(new RepeatTag($this->expr));

            $this->engine->addNodeCompiler(RepeatNode::class, function(RepeatNode $n, CompileContext $c) {
                $c->out->w(str_repeat('    ', 1) . '$__n = (int)(');
                $c->expr($n->countExpr);
                $c->out->w(");\n");
                $c->out->wl(str_repeat('    ', 1) . 'for ($__i = 0; $__i < $__n; $__i++) {');
                $c->node($n->body);
                $c->out->wl(str_repeat('    ', 1) . '}');
            });

            return $this;
        }

        public function enableSwitch(): self
        {
            $this->tags->add(new SwitchTag($this->expr));

            $this->engine->addNodeCompiler(SwitchNode::class, function(SwitchNode $n, CompileContext $c) {
                $c->out->w(str_repeat('    ', 1) . '$__switch = ');
                $c->expr($n->expr);
                $c->out->w(";\n");
                $c->out->wl(str_repeat('    ', 1) . 'switch ($__switch) {');

                foreach ($n->cases as $case) {
                    $c->out->w(str_repeat('    ', 1) . 'case ');
                    $c->expr($case['expr']);
                    $c->out->w(":\n");
                    $c->node($case['body']);
                    $c->out->wl(str_repeat('    ', 2) . 'break;');
                }

                if ($n->defaultBody) {
                    $c->out->wl(str_repeat('    ', 1) . 'default:');
                    $c->node($n->defaultBody);
                    $c->out->wl(str_repeat('    ', 2) . 'break;');
                }

                $c->out->wl(str_repeat('    ', 1) . '}');
            });

            return $this;
        }

    /** Просто прокси: добавляешь pass, если нужен */
    public function addOptimizer(OptimizerPass $pass, int $priority = 0): self
    {
        $this->engine->addOptimizer($pass, $priority);
        return $this;
    }

    /** Иногда удобно прокинуть loader через builder */
    public function withLoader(TemplateLoader $loader): self
    {
        $this->engine->setShared('tpl.loader', $loader);
        return $this;
    }

        /** Вдруг нужно изменить runtime-объект */
        public function withRuntime(Runtime $runtime): self
        {
            $this->engine->setShared('mt.rt', $runtime);
            return $this;
        }

        public function addFunction(string $name, callable $fn): self
        {
            $this->runtime->addFunction($name, $fn);
            return $this;
        }

        public function addFilter(string $name, callable $fn): self
        {
            $this->runtime->addFilter($name, $fn);
            return $this;
        }

        public function engine(): Engine { return $this->engine; }
        public function expr(): ExprParser { return $this->expr; }
        public function tags(): TagRegistry { return $this->tags; }
}
