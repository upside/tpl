<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\IR\Instr;

use Upside\Tpl\Core\IR\Instruction;
use Upside\Tpl\Core\AST\Expr;

/**
 * Instruction representing the output of an expression.
 *
 * Currently, expression compilation is deferred. A later phase will
 * transform the contained expression into executable PHP code.
 */
final class PrintInstruction extends Instruction
{
    public function __construct(public Expr $expr) {}

    public function toPhp(): string
    {
        // TODO: implement expression compilation to PHP.
        return "// TODO: compile expression\n";
    }
}