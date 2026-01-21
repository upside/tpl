<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Optimizer;

use Upside\Tpl\Core\AST\Node;

/* -------------------------------------------------------------------------
 *  Optimizer pipeline
 * ------------------------------------------------------------------------- */

interface OptimizerPass
{
    public function id(): string;

    public function version(): string;

    public function optimize(Node $ast, OptimizeContext $c): Node;
}
