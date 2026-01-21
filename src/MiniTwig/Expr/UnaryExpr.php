<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Expr;

use Upside\Tpl\Core\AST\Expr;

final readonly class UnaryExpr implements Expr { public function __construct(public string $op, public Expr $e) {} }
