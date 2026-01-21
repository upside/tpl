<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Expr;

use Upside\Tpl\Core\AST\Expr;

final readonly class GetAttrExpr implements Expr { public function __construct(public Expr $base, public string $attr) {} }
