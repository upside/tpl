<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\IR;

/**
 * Base class for IR instructions.
 *
 * Every instruction should implement toPhp() to generate PHP code.
 */
abstract class Instruction
{
    /**
     * Convert this instruction to its PHP code representation.
     */
    abstract public function toPhp(): string;
}
