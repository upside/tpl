<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Tags;

use Upside\Tpl\Core\Lexer\TokenStream;
use Upside\Tpl\Core\Parser\ParseContext;
use Upside\Tpl\MiniTwig\Expr\ExprParser;
use Upside\Tpl\MiniTwig\Lexer\Tok;
use Upside\Tpl\MiniTwig\Nodes\RepeatNode;

final class RepeatTag implements TagHandler
{
    public function __construct(private readonly ExprParser $expr) {}

    public function name(): string { return 'repeat'; }

    public function parse(ParseContext $c): RepeatNode
    {
        $count = $this->expr->parse($c->ts);
        $c->ts->expect(Tok::TAG_END);

        $body = $c->subparse(fn(ParseContext $cx) => self::isTagAhead($cx->ts, ['endrepeat']));

        $c->ts->expect(Tok::TAG_START);
        $c->ts->expect(Tok::NAME, 'endrepeat');
        $c->ts->expect(Tok::TAG_END);

        return new RepeatNode($count, $body);
    }

    private static function isTagAhead(TokenStream $ts, array $names): bool
    {
        if (!$ts->test(Tok::TAG_START)) return false;
        $la = $ts->la(1);
        return $la->type === Tok::NAME && in_array((string)$la->value, $names, true);
    }
}
