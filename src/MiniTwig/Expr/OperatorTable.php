<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Expr;

/* -------------------------------------------------------------------------
 *  Expr parser (Pratt)
 * ------------------------------------------------------------------------- */

final class OperatorTable {
    /** @var array<string, array{bp:int, php:string, right:bool}> */
    private array $bin = [];
    /** @var array<string, array{bp:int, php:string}> */
    private array $pre = [];

    public function prefix(string $op, int $bp, string $php): void { $this->pre[$op] = ['bp'=>$bp,'php'=>$php]; }
    public function binary(string $op, int $bp, string $php, bool $right=false): void { $this->bin[$op] = ['bp'=>$bp,'php'=>$php,'right'=>$right]; }

    public function pre(string $op): ?array { return $this->pre[$op] ?? null; }
    public function bin(string $op): ?array { return $this->bin[$op] ?? null; }
}
