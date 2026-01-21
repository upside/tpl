<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\IR\Optimizer;

use Upside\Tpl\Core\Runtime\Engine;

/**
 * Context passed to IR optimization passes.
 *
 * Provides access to the engine and per-pass options.
 */
final class IrOptimizeContext
{
    public function __construct(
        public readonly Engine $env,
        public readonly string $passId
    ) {}

    /**
     * Retrieve options for the current pass from the engine.
     *
     * @return array<mixed>
     */
    public function options(): array
    {
        return $this->env->optimizerOptions($this->passId);
    }
}
