<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Tags;

use Upside\Tpl\Core\Lexer\TokenStream;
use Upside\Tpl\Core\Parser\ParseContext;
use Upside\Tpl\MiniTwig\Lexer\Tok;
use Upside\Tpl\MiniTwig\Nodes\BlockNode;

final class BlockTag implements TagHandler
{
    public function name(): string { return 'block'; }

    public function parse(ParseContext $c): BlockNode
    {
        $name = (string)$c->ts->expect(Tok::NAME)->value;
        $c->ts->expect(Tok::TAG_END);

        $body = $c->subparse(fn(ParseContext $cx) => self::isTagAhead($cx->ts, ['endblock']));

        $c->ts->expect(Tok::TAG_START);
        $c->ts->expect(Tok::NAME, 'endblock');
        if ($c->ts->test(Tok::NAME)) {
            $c->ts->next();
        }
        $c->ts->expect(Tok::TAG_END);

        return new BlockNode($name, $body);
    }

    private static function isTagAhead(TokenStream $ts, array $names): bool
    {
        if (!$ts->test(Tok::TAG_START)) return false;
        $la = $ts->la(1);
        return $la->type === Tok::NAME && in_array((string)$la->value, $names, true);
    }
}
