<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Nodes;

use Upside\Tpl\Core\AST\{Expr, Node, SequenceNode};

final readonly class IfNode implements Node
{
    /** @param list<array{cond:Expr, body:SequenceNode}> $branches */
    public function __construct(public array $branches, public ?SequenceNode $elseBody) {}
}
