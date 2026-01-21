<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Nodes;

use Upside\Tpl\Core\AST\{Expr, Node, SequenceNode};

final readonly class ForNode implements Node {
    public function __construct(
        public string $varName,
        public Expr $iterable,
        public SequenceNode $body,
        public ?SequenceNode $elseBody,
    ) {}
}
