<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Nodes;

use Upside\Tpl\Core\AST\{Expr, Node};

final readonly class AssertNode implements Node
{
    public function __construct(
        public Expr $cond,
        public ?Expr $message
    ) {}
}
