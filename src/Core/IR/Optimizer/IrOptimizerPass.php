<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\IR\Optimizer;

use Upside\Tpl\Core\IR\BasicBlock;

/**
 * Interface for IR optimization passes.
 */
interface IrOptimizerPass
{
    /**
     * Return the unique identifier of this optimization pass.
     */
    public function id(): string;

    /**
     * Return a version string. When the implementation changes,
     * this should be bumped to invalidate cached results.
     */
    public function version(): string;

    /**
     * Optimize a BasicBlock graph.
     *
     * @param BasicBlock $cfg  Entry block of the control-flow graph.
     * @param IrOptimizeContext $context  Context with environment and options.
     * @return BasicBlock  Optimized control-flow graph.
     */
    public function optimize(BasicBlock $cfg, IrOptimizeContext $context): BasicBlock;
}
