<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Optimizer;

use Upside\Tpl\Core\AST\{Node, SequenceNode};
use Upside\Tpl\Core\Optimizer\{OptimizeContext, OptimizerPass};
use Upside\Tpl\Core\Runtime\TemplateLoader;
use Upside\Tpl\MiniTwig\Expr\LitExpr;
use Upside\Tpl\MiniTwig\Nodes\{BlockNode, ExtendsNode, ForNode, IfNode, IncludeNode, RepeatNode, SwitchNode};

/**
 * Inline include:
 *   {% include "header.tpl" %} -> вставка AST включаемого шаблона прямо в текущий AST.
 * Работает только если:
 *   - templateExpr = LitExpr(string)
 *   - в Engine есть shared('tpl.loader') => TemplateLoader
 */
final class InlineIncludePass implements OptimizerPass
{
    public function id(): string
    {
        return 'mt.inline_include';
    }

    public function version(): string
    {
        return '1';
    }

    public function optimize(Node $ast, OptimizeContext $c): Node
    {
        $stack = [$c->src->name];
        return $this->optNode($ast, $c, $stack);
    }

    /** @param list<string> $stack */
    private function optNode(Node $n, OptimizeContext $c, array &$stack): Node
    {
        if ($n instanceof SequenceNode) {
            $nodes = [];
            foreach ($n->nodes as $ch) {
                $opt = $this->optNode($ch, $c, $stack);
                if ($opt instanceof SequenceNode) {
                    foreach ($opt->nodes as $x) $nodes[] = $x;
                } else {
                    $nodes[] = $opt;
                }
            }
            return new SequenceNode($nodes);
        }

        if ($n instanceof IfNode) {
            $branches = [];
            foreach ($n->branches as $br) {
                $branches[] = [
                    'cond' => $br['cond'],
                    'body' => $this->optNode($br['body'], $c, $stack),
                ];
            }
            $else = $n->elseBody ? $this->optNode($n->elseBody, $c, $stack) : null;
            return new IfNode(
                array_map(fn($x) => ['cond' => $x['cond'], 'body' => $x['body'] instanceof SequenceNode ? $x['body'] : new SequenceNode([])], $branches),
                $else instanceof SequenceNode ? $else : null
            );
        }

        if ($n instanceof BlockNode) {
            $body = $this->optNode($n->body, $c, $stack);
            return new BlockNode(
                $n->name,
                $body instanceof SequenceNode ? $body : new SequenceNode([])
            );
        }

        if ($n instanceof ExtendsNode) {
            $blocks = [];
            foreach ($n->blocks as $b) {
                $body = $this->optNode($b->body, $c, $stack);
                $blocks[] = new BlockNode(
                    $b->name,
                    $body instanceof SequenceNode ? $body : new SequenceNode([])
                );
            }
            return new ExtendsNode($n->templateExpr, $blocks);
        }

        if ($n instanceof SwitchNode) {
            $cases = [];
            foreach ($n->cases as $case) {
                $body = $this->optNode($case['body'], $c, $stack);
                $cases[] = [
                    'expr' => $case['expr'],
                    'body' => $body instanceof SequenceNode ? $body : new SequenceNode([]),
                ];
            }
            $default = $n->defaultBody ? $this->optNode($n->defaultBody, $c, $stack) : null;
            return new SwitchNode($n->expr, $cases, $default instanceof SequenceNode ? $default : null);
        }

        if ($n instanceof ForNode) {
            $body = $this->optNode($n->body, $c, $stack);
            $else = $n->elseBody ? $this->optNode($n->elseBody, $c, $stack) : null;
            return new ForNode(
                $n->varName,
                $n->iterable,
                $body instanceof SequenceNode ? $body : new SequenceNode([]),
                $else instanceof SequenceNode ? $else : ($else instanceof Node ? $else : null)
            );
        }

        if ($n instanceof RepeatNode) {
            $body = $this->optNode($n->body, $c, $stack);
            return new RepeatNode($n->countExpr, $body instanceof SequenceNode ? $body : new SequenceNode([]));
        }

        if ($n instanceof IncludeNode) {
            if (!$n->templateExpr instanceof LitExpr) return $n;
            if (!is_string($n->templateExpr->v)) return $n;

            if (!$c->env->hasShared('tpl.loader')) return $n;

            /** @var TemplateLoader $loader */
            $loader = $c->env->shared('tpl.loader');
            $name = $n->templateExpr->v;

            if (in_array($name, $stack, true)) return $n;

            $stack[] = $name;
            try {
                $src = $loader->load($name);

                // Берём сырой AST (без оптимизаций), дальше pipeline оптимизирует общий AST
                $includedAst = $c->env->debugAst($src);

                return $this->optNode($includedAst, $c, $stack);
            } finally {
                array_pop($stack);
            }
        }

        return $n;
    }
}
