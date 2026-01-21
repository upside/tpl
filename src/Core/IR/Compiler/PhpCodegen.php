<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\IR\Compiler;

use Upside\Tpl\Core\IR\BasicBlock;
use Upside\Tpl\Core\IR\Instruction;

/**
 * Simple PHP code generator for the intermediate representation (IR).
 *
 * This generator traverses the control-flow graph of basic blocks and
 * concatenates the PHP code emitted by each instruction. At this stage
 * there is no control flow other than a linear chain of blocks, so the
 * generator simply iterates through the list and appends code.
 */
final class PhpCodegen
{
    /**
     * Generate PHP code from the given IR control-flow graph.
     *
     * @param BasicBlock $cfg The entry block of the IR CFG.
     * @return string Concatenated PHP code for all instructions.
     */
    public function generate(BasicBlock $cfg): string
    {
        $code = '';
        $current = $cfg;
        while ($current !== null) {
            foreach ($current->instructions() as $instr) {
                $code .= $instr->toPhp();
                $code .= "\n";
            }
            $current = $current->next;
        }
        return $code;
    }
}