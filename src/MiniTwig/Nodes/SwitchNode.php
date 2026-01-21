<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Nodes;

use Upside\Tpl\Core\AST\{Expr, Node, SequenceNode};

final readonly class SwitchNode implements Node
{
    /** @param list<array{expr:Expr, body:SequenceNode}> $cases */
    public function __construct(
        public Expr $expr,
        public array $cases,
        public ?SequenceNode $defaultBody
    ) {}
}
