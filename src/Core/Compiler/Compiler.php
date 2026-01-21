<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Compiler;

use Upside\Tpl\Core\AST\{Expr, Node, SequenceNode};
use Upside\Tpl\Core\Lexer\Source;
use Upside\Tpl\Core\Runtime\Engine;

final class Compiler
{
    /** @param list<callable(PhpEmitter):void> $prologues */
    public function __construct(
        private readonly CompileRegistry $nodeReg,
        private readonly CompileRegistry $exprReg,
        private readonly array $prologues
    ) {}

    public function compileToPhp(Source $src, Node $ast, Engine $env): string
    {
        $em = new PhpEmitter();

        $em->wl('<?php');
        $em->wl('declare(strict_types=1);');
        $em->wl('');
        $em->wl('// compiled from: ' . addslashes($src->name));
        $em->wl('return static function(array $context, \\Upside\\Tpl\\Core\\Runtime\\Engine $env): string {');
        $em->ind(1);
        $em->wl('$__out = \'\';');

        foreach ($this->prologues as $p) $p($em);

        $ctx = new CompileContext(
            env: $env,
            out: $em,
            emitNode: function (Node $n) use (&$ctx) {
                $this->emitNode($n, $ctx);
            },
            emitExpr: function (Expr $e) use (&$ctx) {
                $this->emitExpr($e, $ctx);
            },
        );

        $this->emitNode($ast, $ctx);

        $em->wl('return $__out;');
        $em->ind(-1);
        $em->wl('};');

        return $em->code();
    }

    private function emitNode(Node $n, CompileContext $c): void
    {
        if ($n instanceof SequenceNode) {
            foreach ($n->nodes as $ch) $this->emitNode($ch, $c);
            return;
        }
        $fn = $this->nodeReg->get($n);
        $fn($n, $c);
    }

    private function emitExpr(Expr $e, CompileContext $c): void
    {
        $fn = $this->exprReg->get($e);
        $fn($e, $c);
    }
}
