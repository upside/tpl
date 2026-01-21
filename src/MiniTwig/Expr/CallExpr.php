<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Expr;

use Upside\Tpl\Core\AST\Expr;

final readonly class CallExpr implements Expr
{
    /** @param list<Expr> $args */
    public function __construct(
        public Expr $callee,
        public array $args
    ) {}
}
