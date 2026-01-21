<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Parser;

use Upside\Tpl\Core\AST\SequenceNode;
use Upside\Tpl\Core\Lexer\CoreTok;
use Upside\Tpl\Core\Lexer\TokenStream;
use Upside\Tpl\Core\Runtime\Engine;

final class Parser
{
    public function __construct(private readonly ParserRegistry $reg) {}

    public function parse(TokenStream $ts, Engine $env): SequenceNode
    {
        $ctx = new ParseContext($this, $ts, $env);
        return $this->subparse($ctx, fn(ParseContext $c) => $c->ts->test(CoreTok::EOF));
    }

    /** @param callable(ParseContext):bool $stop */
    public function subparse(ParseContext $ctx, callable $stop): SequenceNode
    {
        $nodes = [];

        while (!$stop($ctx)) {
            $parsed = false;
            foreach ($this->reg->all() as $p) {
                if (!$p->supports($ctx)) continue;
                $nodes[] = $p->parse($ctx);
                $parsed = true;
                break;
            }
            if (!$parsed) {
                $t = $ctx->ts->cur();
                throw new ParserException("No statement parser for token {$t->type}", $ctx->ts->source(), $t->span);
            }
        }

        return new SequenceNode($nodes);
    }
}
