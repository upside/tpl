<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Expr;

use Upside\Tpl\Core\AST\Expr;

final readonly class BinaryExpr implements Expr { public function __construct(public Expr $l, public string $op, public Expr $r) {} }
