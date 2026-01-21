<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Tags;

use Upside\Tpl\Core\AST\Node;
use Upside\Tpl\Core\Lexer\TokenStream;
use Upside\Tpl\Core\Parser\ParseContext;
use Upside\Tpl\MiniTwig\Expr\{ExprParser, UnaryExpr};
use Upside\Tpl\MiniTwig\Lexer\Tok;
use Upside\Tpl\MiniTwig\Nodes\IfNode;

final class UnlessTag implements TagHandler
{
    public function __construct(private readonly ExprParser $expr) {}

    public function name(): string
    {
        return 'unless';
    }

    public function parse(ParseContext $c): Node
    {
        $cond = $this->expr->parse($c->ts);
        $c->ts->expect(Tok::TAG_END);

        $body = $c->subparse(fn(ParseContext $cx) => self::isTagAhead($cx->ts, ['else', 'endunless']));

        $elseBody = null;
        if (self::isTagAhead($c->ts, ['else'])) {
            $c->ts->expect(Tok::TAG_START);
            $c->ts->expect(Tok::NAME, 'else');
            $c->ts->expect(Tok::TAG_END);

            $elseBody = $c->subparse(fn(ParseContext $cx) => self::isTagAhead($cx->ts, ['endunless']));
        }

        $c->ts->expect(Tok::TAG_START);
        $c->ts->expect(Tok::NAME, 'endunless');
        $c->ts->expect(Tok::TAG_END);

        $neg = new UnaryExpr('not', $cond);
        return new IfNode([['cond' => $neg, 'body' => $body]], $elseBody);
    }

    private static function isTagAhead(TokenStream $ts, array $names): bool
    {
        if (!$ts->test(Tok::TAG_START)) return false;
        $la = $ts->la(1);
        return $la->type === Tok::NAME && in_array((string)$la->value, $names, true);
    }
}
