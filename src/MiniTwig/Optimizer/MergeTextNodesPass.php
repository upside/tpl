<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Optimizer;

use Upside\Tpl\Core\AST\{Node, SequenceNode};
use Upside\Tpl\Core\Optimizer\{OptimizeContext, OptimizerPass};
use Upside\Tpl\MiniTwig\Nodes\{BlockNode, ExtendsNode, ForNode, IfNode, RepeatNode, SwitchNode, TextNode};

/* -------------------------------------------------------------------------
 *  Оптимизации MiniTwig (как примеры)
 * ------------------------------------------------------------------------- */

final class MergeTextNodesPass implements OptimizerPass {
    public function id(): string { return 'mt.merge_text'; }
    public function version(): string { return '1'; }

    public function optimize(Node $ast, OptimizeContext $c): Node {
        return $this->optNode($ast);
    }

    private function optNode(Node $n): Node {
        if ($n instanceof SequenceNode) return $this->optSeq($n);

            if ($n instanceof IfNode) {
                $branches = [];
                foreach ($n->branches as $br) {
                    $branches[] = ['cond'=>$br['cond'], 'body'=>$this->optSeq($br['body'])];
                }
                $else = $n->elseBody ? $this->optSeq($n->elseBody) : null;
                return new IfNode($branches, $else);
            }

            if ($n instanceof BlockNode) {
                return new BlockNode($n->name, $this->optSeq($n->body));
            }

            if ($n instanceof ExtendsNode) {
                $blocks = [];
                foreach ($n->blocks as $b) {
                    $blocks[] = new BlockNode($b->name, $this->optSeq($b->body));
                }
                return new ExtendsNode($n->templateExpr, $blocks);
            }

            if ($n instanceof SwitchNode) {
                $cases = [];
                foreach ($n->cases as $case) {
                    $cases[] = ['expr' => $case['expr'], 'body' => $this->optSeq($case['body'])];
                }
                $default = $n->defaultBody ? $this->optSeq($n->defaultBody) : null;
                return new SwitchNode($n->expr, $cases, $default);
            }

            if ($n instanceof ForNode) {
                $body = $this->optSeq($n->body);
                $else = $n->elseBody ? $this->optSeq($n->elseBody) : null;
                return new ForNode($n->varName, $n->iterable, $body, $else);
            }

            if ($n instanceof RepeatNode) {
                return new RepeatNode($n->countExpr, $this->optSeq($n->body));
            }

            return $n;
        }

    private function optSeq(SequenceNode $seq): SequenceNode {
        $out = [];
        foreach ($seq->nodes as $node) {
            $node = $this->optNode($node);
            $last = $out ? $out[count($out)-1] : null;

            if ($node instanceof TextNode && $last instanceof TextNode) {
                $out[count($out)-1] = new TextNode($last->text . $node->text);
            } else {
                $out[] = $node;
            }
        }
        return new SequenceNode($out);
    }
}
