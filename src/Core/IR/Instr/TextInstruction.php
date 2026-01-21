<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\IR\Instr;

use Upside\Tpl\Core\IR\Instruction;

/**
 * Instruction representing static text output.
 *
 * Generates PHP code to append a literal string to the output buffer.
 */
final class TextInstruction extends Instruction
{
    public function __construct(private string $text) {}

    public function toPhp(): string
    {
        // Escape text for a PHP single-quoted string literal
        $escaped = addslashes($this->text);
        return "\$out .= '" . $escaped . "';";
    }
}