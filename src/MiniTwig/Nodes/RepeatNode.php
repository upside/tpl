<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Nodes;

use Upside\Tpl\Core\AST\{Expr, Node, SequenceNode};

final readonly class RepeatNode implements Node
{
    public function __construct(
        public Expr $countExpr,
        public SequenceNode $body
    ) {}
}
