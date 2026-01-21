<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Compiler;

use Upside\Tpl\Core\AST\{Expr, Node};
use Upside\Tpl\Core\Runtime\Engine;

final class CompileContext {
    public function __construct(
        public readonly Engine $env,
        public readonly PhpEmitter $out,
        private readonly \Closure $emitNode,
        private readonly \Closure $emitExpr
    ) {}

    public function node(Node $n): void { ($this->emitNode)($n); }
    public function expr(Expr $e): void { ($this->emitExpr)($e); }
}
