<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Tags;

use Upside\Tpl\Core\AST\Node;
use Upside\Tpl\Core\Parser\{ParseContext, ParserException, StatementParser};
use Upside\Tpl\MiniTwig\Lexer\Tok;

final class TagStmtParser implements StatementParser
{
    public function __construct(private readonly TagRegistry $tags) {}

    public function supports(ParseContext $c): bool
    {
        return $c->ts->test(Tok::TAG_START);
    }

    public function parse(ParseContext $c): Node
    {
        $c->ts->next();
        $name = (string)$c->ts->expect(Tok::NAME)->value;

        $h = $this->tags->get($name);
        if (!$h) {
            $t = $c->ts->cur();
            throw new ParserException("Unknown tag: {$name}", $c->ts->source(), $t->span);
        }

        return $h->parse($c);
    }
}
