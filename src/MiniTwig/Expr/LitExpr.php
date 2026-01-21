<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Expr;

use Upside\Tpl\Core\AST\Expr;

// expressions
final readonly class LitExpr implements Expr
{
    public function __construct(public mixed $v) {}
}
