<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Nodes;

use Upside\Tpl\Core\AST\{Expr, Node};

final readonly class DebugNode implements Node
{
    public function __construct(public Expr $expr) {}
}
