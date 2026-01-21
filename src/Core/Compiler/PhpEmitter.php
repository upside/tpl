<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Compiler;

/* -------------------------------------------------------------------------
 *  Compiler (AST -> PHP code)
 * ------------------------------------------------------------------------- */

final class PhpEmitter {
    private string $code = '';
    private int $indent = 0;

    public function ind(int $d): void { $this->indent += $d; }
    public function wl(string $s=''): void { $this->code .= str_repeat('    ', $this->indent) . $s . "\n"; }
    public function w(string $s): void { $this->code .= $s; }
    public function code(): string { return $this->code; }
}
