<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\IR;

use Upside\Tpl\Core\AST\Node;

/**
 * Builds an intermediate representation (IR) from the AST.
 */
class IRBuilder
{
    /**
     * Build a chain of basic blocks from AST.
     *
     * @param Node $ast
     * @return BasicBlock|null
     */
    public function build(Node $ast): ?BasicBlock
    {
        // TODO: implement actual conversion
        return null;
    }
}
