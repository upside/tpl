<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\IR;

use Upside\Tpl\Core\AST\Node;
use Upside\Tpl\Core\IR\BasicBlock;
use Upside\Tpl\Core\AST\SequenceNode;
use Upside\Tpl\MiniTwig\Nodes\TextNode as MiniTextNode;
use Upside\Tpl\MiniTwig\Nodes\PrintNode as MiniPrintNode;
use Upside\Tpl\Core\IR\Instr\TextInstruction;
use Upside\Tpl\Core\IR\Instr\PrintInstruction;

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
        // Create an entry block and populate it with instructions
        $entry = new BasicBlock();
        $this->emit($ast, $entry);
        return $entry;
    }

    /**
     * Recursively emit IR instructions for AST nodes into the given block.
     *
     * @param Node $node
     * @param BasicBlock $block
     */
    private function emit(Node $node, BasicBlock $block): void
    {
        // Sequence nodes: emit each child in order
        if ($node instanceof SequenceNode) {
            foreach ($node->nodes as $child) {
                $this->emit($child, $block);
            }
            return;
        }

        // Text nodes from MiniTwig
        if ($node instanceof MiniTextNode) {
            $block->addInstruction(new TextInstruction($node->text));
            return;
        }

        // Print nodes from MiniTwig
        if ($node instanceof MiniPrintNode) {
            $block->addInstruction(new PrintInstruction($node->expr));
            return;
        }

        // For other node types, IR emission is not yet implemented
    }
}
