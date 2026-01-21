<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\IR;

/**
 * A basic block is a sequence of instructions with a single successor.
 */
class BasicBlock
{
    /**
     * @var Instruction[]
     */
    private array $instructions = [];

    /**
     * @var BasicBlock|null
     */
    public ?BasicBlock $next = null;

    /**
     * Append an instruction to the block.
     */
    public function addInstruction(Instruction $instruction): void
    {
        $this->instructions[] = $instruction;
    }

    /**
     * Get the instructions.
     *
     * @return Instruction[]
     */
    public function instructions(): array
    {
        return $this->instructions;
    }
}
