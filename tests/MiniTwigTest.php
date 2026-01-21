<?php
declare(strict_types=1);

namespace Upside\Tpl\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Upside\Tpl\Core\AST\SequenceNode;
use Upside\Tpl\Core\Diagnostics\TplException;
use Upside\Tpl\Core\Lexer\LexerException;
use Upside\Tpl\Core\Lexer\Source;
use Upside\Tpl\Core\Optimizer\OptimizerPass;
use Upside\Tpl\Core\Parser\ParserException;
use Upside\Tpl\Core\Runtime\{Engine, TemplateLoader};
use Upside\Tpl\MiniTwig\Builder\MiniTwigBuilder;
use Upside\Tpl\MiniTwig\Lexer\Syntax;
use Upside\Tpl\MiniTwig\Nodes\{ForNode, IfNode, IncludeNode, TextNode};
use Upside\Tpl\MiniTwig\Optimizer\{InlineIncludePass, MergeTextNodesPass};
use Upside\Tpl\MiniTwig\Runtime\Runtime;

final class MiniTwigTest extends TestCase
{
    /** @param array<string, string> $templates */
    private function buildEngine(array $templates = [], ?Runtime $runtime = null): Engine
    {
        return $this->buildEngineWithOptimizers($templates, [], null, $runtime);
    }

    /**
     * @param array<string, string> $templates
     * @param list<OptimizerPass> $passes
     */
    private function buildEngineWithOptimizers(array $templates, array $passes, ?string $cacheDir = null, ?Runtime $runtime = null): Engine
    {
        $cacheDir ??= sys_get_temp_dir() . '/tpl-cache-' . bin2hex(random_bytes(4));
        $engine = new Engine($cacheDir, debug: true);
        $runtime ??= new Runtime();

        $builder = new MiniTwigBuilder($engine, new Syntax(), $runtime);
        $builder->registerBase()
            ->enableIf()
            ->enableFor()
            ->enableInclude()
            ->enableInheritance()
            ->enableUnless()
            ->enableAssertDebug()
            ->enableSwitch()
            ->enableRepeat();
        foreach ($passes as $pass) {
            $builder->addOptimizer($pass);
        }
        $builder->withLoader(new ArrayLoader($templates));

        return $engine;
    }

    public function testRenderText(): void
    {
        $engine = $this->buildEngine();
        $this->assertSame('Hello', $engine->render('Hello'));
    }

    public function testRenderVariableWithEscape(): void
    {
        $engine = $this->buildEngine();
        $out = $engine->render('Hi {{ name }}', ['name' => '<b>Bob</b>']);
        $this->assertSame('Hi &lt;b&gt;Bob&lt;/b&gt;', $out);
    }

    public function testIfElseifElse(): void
    {
        $engine = $this->buildEngine();
        $tpl = '{% if a %}A{% elseif b %}B{% else %}C{% endif %}';
        $this->assertSame('B', $engine->render($tpl, ['a' => false, 'b' => true]));
    }

    public function testForLoopWithElse(): void
    {
        $engine = $this->buildEngine();
        $tpl = '{% for x in items %}{{ x }}{% else %}empty{% endfor %}';
        $this->assertSame('12', $engine->render($tpl, ['items' => [1, 2]]));
        $this->assertSame('empty', $engine->render($tpl, ['items' => []]));
    }

    public function testAttributeAccess(): void
    {
        $engine = $this->buildEngine();
        $tpl = '{{ user.name }}';
        $this->assertSame('Ada', $engine->render($tpl, ['user' => ['name' => 'Ada']]));
    }

    public function testExpressions(): void
    {
        $engine = $this->buildEngine();
        $this->assertSame('14', $engine->render('{{ 2 + 3 * 4 }}'));
        $this->assertSame('ab', $engine->render('{{ "a" ~ "b" }}'));
    }

    public function testFunctionCall(): void
    {
        $runtime = new Runtime();
        $runtime->addFunction('sum', fn(int $a, int $b): int => $a + $b);
        $engine = $this->buildEngine([], $runtime);
        $this->assertSame('5', $engine->render('{{ sum(2, 3) }}'));
    }

    public function testFilterSimple(): void
    {
        $runtime = new Runtime();
        $runtime->addFilter('upper', fn(string $v): string => strtoupper($v));
        $engine = $this->buildEngine([], $runtime);
        $this->assertSame('BOB', $engine->render('{{ name|upper }}', ['name' => 'Bob']));
    }

    public function testFilterWithArgsAndChain(): void
    {
        $runtime = new Runtime();
        $runtime->addFilter('wrap', fn(string $v, string $l, string $r): string => $l . $v . $r);
        $runtime->addFilter('upper', fn(string $v): string => strtoupper($v));
        $engine = $this->buildEngine([], $runtime);
        $tpl = '{{ name|upper|wrap("[", "]") }}';
        $this->assertSame('[BOB]', $engine->render($tpl, ['name' => 'Bob']));
    }

    public function testAssertTagTrue(): void
    {
        $engine = $this->buildEngine();
        $tpl = 'A{% assert ok %}B';
        $this->assertSame('AB', $engine->render($tpl, ['ok' => true]));
    }

    public function testAssertTagThrows(): void
    {
        $engine = $this->buildEngine();
        $this->expectException(TplException::class);
        $engine->render('{% assert ok, "nope" %}', ['ok' => false]);
    }

    public function testDebugTagOutputsInDebugMode(): void
    {
        $engine = $this->buildEngine();
        $out = $engine->render('A{% debug name %}B', ['name' => 'Bob']);
        $this->assertStringContainsString('A', $out);
        $this->assertStringContainsString('B', $out);
        $this->assertStringContainsString('Bob', $out);
        $this->assertStringContainsString('mt-debug', $out);
    }

    public function testRepeatTag(): void
    {
        $engine = $this->buildEngine();
        $tpl = '{% repeat n %}x{% endrepeat %}';
        $this->assertSame('xxx', $engine->render($tpl, ['n' => 3]));
        $this->assertSame('', $engine->render($tpl, ['n' => 0]));
    }

    public function testSwitchCaseDefault(): void
    {
        $engine = $this->buildEngine();
        $tpl = '{% switch v %}{% case 1 %}one{% case 2 %}two{% default %}other{% endswitch %}';
        $this->assertSame('two', $engine->render($tpl, ['v' => 2]));
        $this->assertSame('other', $engine->render($tpl, ['v' => 3]));
    }

    public function testNestedRepeatSwitchIf(): void
    {
        $engine = $this->buildEngine();
        $tpl = '{% repeat n %}{% switch v %}{% case 1 %}A{% default %}{% if ok %}B{% endif %}{% endswitch %}{% endrepeat %}';
        $this->assertSame('AA', $engine->render($tpl, ['n' => 2, 'v' => 1, 'ok' => true]));
        $this->assertSame('BB', $engine->render($tpl, ['n' => 2, 'v' => 0, 'ok' => true]));
    }

    public function testSwitchContainsRepeat(): void
    {
        $engine = $this->buildEngine();
        $tpl = '{% switch v %}{% case 1 %}{% repeat n %}x{% endrepeat %}{% default %}y{% endswitch %}';
        $this->assertSame('xx', $engine->render($tpl, ['v' => 1, 'n' => 2]));
        $this->assertSame('y', $engine->render($tpl, ['v' => 2, 'n' => 2]));
    }

    public function testExpressionPrecedence(): void
    {
        $engine = $this->buildEngine();
        $this->assertSame('7', $engine->render('{{ 1 + 2 * 3 }}'));
        $this->assertSame('9', $engine->render('{{ (1 + 2) * 3 }}'));
        $this->assertSame('1', $engine->render('{{ true or false and false }}'));
    }

    public function testIncludeTag(): void
    {
        $templates = [
            'base' => 'Hello {% include "child" %}!',
            'child' => '{{ name }}',
        ];
        $engine = $this->buildEngine($templates);

        $out = $engine->render(new Source('base', $templates['base']), ['name' => 'Bob']);
        $this->assertSame('Hello Bob!', $out);
    }

    public function testIncludeTagWithVariableName(): void
    {
        $templates = [
            'child' => 'OK',
        ];
        $engine = $this->buildEngine($templates);

        $out = $engine->render('{% include name %}', ['name' => 'child']);
        $this->assertSame('OK', $out);
    }

    public function testVariableOutputInsideIfBlock(): void
    {
        $engine = $this->buildEngine();
        $tpl = '{% if ok %}Hello {{ name }}{% endif %}';
        $this->assertSame('Hello Ada', $engine->render($tpl, ['ok' => true, 'name' => 'Ada']));
        $this->assertSame('', $engine->render($tpl, ['ok' => false, 'name' => 'Ada']));
    }

    public function testIncludeInsideIfBlock(): void
    {
        $templates = [
            'child' => 'World',
        ];
        $engine = $this->buildEngine($templates);
        $tpl = 'Hello {% if ok %}{% include "child" %}{% endif %}';
        $this->assertSame('Hello World', $engine->render($tpl, ['ok' => true]));
        $this->assertSame('Hello ', $engine->render($tpl, ['ok' => false]));
    }

    public function testIncludeVariableInsideIfBlock(): void
    {
        $templates = [
            'a' => 'A',
            'b' => 'B',
        ];
        $engine = $this->buildEngine($templates);
        $tpl = '{% if ok %}{% include name %}{% else %}X{% endif %}';
        $this->assertSame('A', $engine->render($tpl, ['ok' => true, 'name' => 'a']));
        $this->assertSame('X', $engine->render($tpl, ['ok' => false, 'name' => 'b']));
    }

    public function testTemplateInheritanceWithBlockOverride(): void
    {
        $templates = [
            'base' => 'Header {% block content %}base{% endblock %} Footer',
            'child' => '{% extends "base" %}{% block content %}child{% endblock %}',
        ];
        $engine = $this->buildEngine($templates);
        $out = $engine->render(new Source('child', $templates['child']));
        $this->assertSame('Header child Footer', $out);
    }

    public function testTemplateInheritanceWithParentCall(): void
    {
        $templates = [
            'base' => 'A{% block content %}base{% endblock %}B',
            'child' => '{% extends "base" %}{% block content %}child-{{ parent() }}{% endblock %}',
        ];
        $engine = $this->buildEngine($templates);
        $out = $engine->render(new Source('child', $templates['child']));
        $this->assertSame('Achild-baseB', $out);
    }

    public function testParentCallWithoutInheritanceThrows(): void
    {
        $engine = $this->buildEngine();
        $this->expectException(TplException::class);
        $engine->render('{% block content %}{{ parent() }}{% endblock %}');
    }

    public function testTemplateInheritanceUsesDefaultBlock(): void
    {
        $templates = [
            'base' => 'A{% block content %}base{% endblock %}B',
        ];
        $engine = $this->buildEngine($templates);
        $out = $engine->render(new Source('base', $templates['base']));
        $this->assertSame('AbaseB', $out);
    }

    public function testExtendsWithDynamicName(): void
    {
        $templates = [
            'base' => 'A{% block content %}base{% endblock %}B',
        ];
        $engine = $this->buildEngine($templates);
        $tpl = '{% extends name %}{% block content %}child{% endblock %}';
        $this->assertSame('AchildB', $engine->render($tpl, ['name' => 'base']));
    }

    public function testExtendsDisallowsNonWhitespaceOutsideBlocks(): void
    {
        $templates = [
            'base' => 'A{% block content %}base{% endblock %}B',
        ];
        $engine = $this->buildEngine($templates);
        $tpl = '{% extends "base" %}X{% block content %}child{% endblock %}';
        $this->expectException(ParserException::class);
        $engine->render($tpl);
    }

    public function testExtendsAllowsLeadingWhitespace(): void
    {
        $templates = [
            'base' => 'A{% block content %}base{% endblock %}B',
        ];
        $engine = $this->buildEngine($templates);
        $tpl = "  \n\t{% extends \"base\" %}{% block content %}child{% endblock %}";
        $this->assertSame('AchildB', $engine->render($tpl));
    }

    public function testUnlessTag(): void
    {
        $engine = $this->buildEngine();
        $tpl = '{% unless ok %}no{% else %}yes{% endunless %}';
        $this->assertSame('no', $engine->render($tpl, ['ok' => false]));
        $this->assertSame('yes', $engine->render($tpl, ['ok' => true]));
    }

    public function testIfInsideForWithInclude(): void
    {
        $templates = [
            'item' => '{{ x }}',
        ];
        $engine = $this->buildEngine($templates);
        $tpl = '{% for x in items %}{% if x %}{% include "item" %}{% endif %}{% endfor %}';
        $this->assertSame('12', $engine->render($tpl, ['items' => [1, 0, 2]]));
    }

    public function testNestedIfForIncludeWithShadowing(): void
    {
        $templates = [
            'suffix' => '-',
        ];
        $engine = $this->buildEngine($templates);
        $tpl = '{% if ok %}{% for x in items %}{% if x %}{{ x }}{% include "suffix" %}{% endif %}{% endfor %}{% endif %}{{ x }}';
        $out = $engine->render($tpl, ['ok' => true, 'x' => 'out', 'items' => [1, 0, 2]]);
        $this->assertSame('1-2-out', $out);
    }

    public function testIncludeWithVariableInsideForAndIf(): void
    {
        $templates = [
            'a' => 'A',
            'b' => 'B',
        ];
        $engine = $this->buildEngine($templates);
        $tpl = '{% for item in items %}{% if item.show %}{% include item.tpl %}{% endif %}{% endfor %}';
        $items = [
            ['tpl' => 'a', 'show' => true],
            ['tpl' => 'b', 'show' => false],
            ['tpl' => 'b', 'show' => true],
        ];
        $this->assertSame('AB', $engine->render($tpl, ['items' => $items]));
    }

    public function testNestedIncludeUsesSameContext(): void
    {
        $templates = [
            'base' => 'Start {% include "child" %} End',
            'child' => 'Child {{ name }}{% include "grand" %}',
            'grand' => '{{ suffix }}',
        ];
        $engine = $this->buildEngine($templates);
        $out = $engine->render(new Source('base', $templates['base']), ['name' => 'Bob', 'suffix' => '!']);
        $this->assertSame('Start Child Bob! End', $out);
    }

    public function testIfConditionWithAttributeExpression(): void
    {
        $engine = $this->buildEngine();
        $tpl = '{% if user.isAdmin %}admin{% else %}user{% endif %}';
        $this->assertSame('admin', $engine->render($tpl, ['user' => new TestUser('Ada', true)]));
        $this->assertSame('user', $engine->render($tpl, ['user' => new TestUser('Bob', false)]));
    }

    public function testInlineIncludeCycleDoesNotInlineForever(): void
    {
        $templates = [
            'a' => 'A{% include "b" %}',
            'b' => 'B{% include "a" %}',
        ];
        $engine = $this->buildEngineWithOptimizers($templates, [new InlineIncludePass()]);

        $ast = $engine->debugOptimizedAst(new Source('a', $templates['a']));
        $this->assertTrue($this->astHasIncludeNode($ast));
    }

    public function testDebugDumpAstContainsNodeNames(): void
    {
        $engine = $this->buildEngine();
        $dump = $engine->debugDumpAst('Hi {{ name }}');
        $this->assertStringContainsString('SequenceNode', $dump);
        $this->assertStringContainsString('TextNode', $dump);
        $this->assertStringContainsString('PrintNode', $dump);
    }

    public function testCompiledTemplateWrittenToCache(): void
    {
        $cacheDir = sys_get_temp_dir() . '/tpl-cache-test-' . bin2hex(random_bytes(4));
        $engine = $this->buildEngineWithOptimizers([], [], $cacheDir);
        $engine->render(new Source('tpl1', 'Hello'));

        $files = $this->listCacheFiles($cacheDir);
        $this->assertCount(1, $files);

        $base = basename($files[0], '.php');
        $this->assertSame(16, strlen($base));
        $this->assertTrue(ctype_xdigit($base));

        $php = file_get_contents($files[0]);
        $this->assertIsString($php);
        $this->assertStringContainsString('compiled from: tpl1', $php);
        $this->assertStringContainsString('return static function', $php);
    }

    public function testCacheKeyChangesWhenOptimizerAdded(): void
    {
        $cacheDir = sys_get_temp_dir() . '/tpl-cache-test-' . bin2hex(random_bytes(4));
        $engine = $this->buildEngineWithOptimizers([], [], $cacheDir);
        $engine->render('Hello');
        $this->assertCount(1, $this->listCacheFiles($cacheDir));

        $engine->addOptimizer(new MergeTextNodesPass());
        $engine->render('Hello');
        $this->assertCount(2, $this->listCacheFiles($cacheDir));
    }

    public function testInlineIncludeOptimizerRemovesIncludeNode(): void
    {
        $templates = [
            'base' => 'Hello {% include "child" %}!',
            'child' => 'World',
        ];
        $engine = $this->buildEngineWithOptimizers($templates, [new InlineIncludePass()]);

        $ast = $engine->debugOptimizedAst(new Source('base', $templates['base']));
        $this->assertFalse($this->astHasIncludeNode($ast));
        $this->assertSame('Hello World!', $engine->render(new Source('base', $templates['base'])));
    }

    public function testMergeTextNodesAfterInlineInclude(): void
    {
        $templates = [
            'base' => 'Hello {% include "child" %}!',
            'child' => 'World',
        ];
        $engine = $this->buildEngineWithOptimizers($templates, [new InlineIncludePass(), new MergeTextNodesPass()]);

        $ast = $engine->debugOptimizedAst(new Source('base', $templates['base']));
        $this->assertInstanceOf(SequenceNode::class, $ast);
        $this->assertCount(1, $ast->nodes);
        $this->assertInstanceOf(TextNode::class, $ast->nodes[0]);
        $this->assertSame('Hello World!', $ast->nodes[0]->text);
    }

    public function testCommentIsIgnored(): void
    {
        $engine = $this->buildEngine();
        $this->assertSame('HelloWorld', $engine->render('Hello{# comment #}World'));
    }

    public function testAttributeAccessOnObjectGetter(): void
    {
        $engine = $this->buildEngine();
        $user = new TestUser('Ada');
        $this->assertSame('Ada', $engine->render('{{ user.name }}', ['user' => $user]));
    }

    public function testForLoopRestoresOuterContext(): void
    {
        $engine = $this->buildEngine();
        $tpl = '{{ x }}:{% for x in items %}{{ x }}{% endfor %}:{{ x }}';
        $out = $engine->render($tpl, ['x' => 'out', 'items' => [1]]);
        $this->assertSame('out:1:out', $out);
    }

    public function testUnaryNotAndNullLiteral(): void
    {
        $engine = $this->buildEngine();
        $this->assertSame('1', $engine->render('{{ not false }}'));
        $this->assertSame('', $engine->render('{{ null }}'));
    }

    public function testPrettyDiagnosticForLexerError(): void
    {
        $engine = $this->buildEngine();
        try {
            $engine->render("Hello\n{{ @ }}");
            $this->fail('Expected LexerException');
        } catch (LexerException $e) {
            $pretty = $e->pretty();
            $this->assertStringContainsString('Template: inline', $pretty);
            $this->assertStringContainsString('At: line 2', $pretty);
            $this->assertStringContainsString('^', $pretty);
        }
    }

    public function testUnknownTagThrowsParserException(): void
    {
        $engine = $this->buildEngine();
        $this->expectException(ParserException::class);
        $engine->render('{% unknown %}');
    }

    public function testUnclosedStringThrowsLexerException(): void
    {
        $engine = $this->buildEngine();
        $this->expectException(LexerException::class);
        $engine->render('{{ "abc }}');
    }

    private function astHasIncludeNode(mixed $node): bool
    {
        if ($node instanceof IncludeNode) return true;

        if ($node instanceof SequenceNode) {
            foreach ($node->nodes as $child) {
                if ($this->astHasIncludeNode($child)) return true;
            }
            return false;
        }

        if ($node instanceof IfNode) {
            foreach ($node->branches as $branch) {
                if ($this->astHasIncludeNode($branch['body'])) return true;
            }
            return $node->elseBody ? $this->astHasIncludeNode($node->elseBody) : false;
        }

        if ($node instanceof ForNode) {
            if ($this->astHasIncludeNode($node->body)) return true;
            return $node->elseBody ? $this->astHasIncludeNode($node->elseBody) : false;
        }

        return false;
    }

    /** @return list<string> */
    private function listCacheFiles(string $dir): array
    {
        if (!is_dir($dir)) return [];
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            if ($file->getExtension() !== 'php') continue;
            $out[] = $file->getPathname();
        }
        sort($out);
        return $out;
    }
}

final class ArrayLoader implements TemplateLoader
{
    /** @param array<string, string> $templates */
    public function __construct(private array $templates) {}

    public function load(string $name): Source
    {
        if (!array_key_exists($name, $this->templates)) {
            throw new RuntimeException("Template not found: {$name}");
        }
        return new Source($name, $this->templates[$name]);
    }
}

final class TestUser
{
    public function __construct(
        private string $name,
        private bool $admin = false
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    public function getIsAdmin(): bool
    {
        return $this->admin;
    }
}
