<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Compiler;

use Upside\Tpl\Core\AST\{Expr, Node};
use Upside\Tpl\Core\Runtime\Engine;

final readonly class CompileContext
{
    public function __construct(
        public Engine $env,
        public PhpEmitter $out,
        private \Closure $emitNode,
        private \Closure $emitExpr
    ) {}

    public function node(Node $n): void
    {
        ($this->emitNode)($n);
    }

    public function expr(Expr $e): void
    {
        ($this->emitExpr)($e);
    }
}
