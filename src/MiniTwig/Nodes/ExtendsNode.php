<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Nodes;

use Upside\Tpl\Core\AST\{Expr, Node};

final readonly class ExtendsNode implements Node
{
    /** @param list<BlockNode> $blocks */
    public function __construct(
        public Expr $templateExpr,
        public array $blocks,
    ) {}
}
