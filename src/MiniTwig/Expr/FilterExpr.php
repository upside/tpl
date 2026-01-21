<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Expr;

use Upside\Tpl\Core\AST\Expr;

final readonly class FilterExpr implements Expr
{
    /** @param list<Expr> $args */
    public function __construct(
        public Expr $input,
        public string $name,
        public array $args,
    ) {}
}
