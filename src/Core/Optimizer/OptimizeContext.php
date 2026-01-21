<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Optimizer;

use Upside\Tpl\Core\Lexer\Source;
use Upside\Tpl\Core\Runtime\Engine;

final class OptimizeContext {
    public function __construct(
        public readonly Engine $env,
        public readonly Source $src,
        public readonly string $passId
    ) {}

    public function options(): array {
        return $this->env->optimizerOptions($this->passId);
    }
}
