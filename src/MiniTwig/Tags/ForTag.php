<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Tags;

use Upside\Tpl\Core\AST\Node;
use Upside\Tpl\Core\Lexer\TokenStream;
use Upside\Tpl\Core\Parser\ParseContext;
use Upside\Tpl\MiniTwig\Expr\ExprParser;
use Upside\Tpl\MiniTwig\Lexer\Tok;
use Upside\Tpl\MiniTwig\Nodes\ForNode;

final class ForTag implements TagHandler {
    public function __construct(private readonly ExprParser $expr) {}
    public function name(): string { return 'for'; }

    public function parse(ParseContext $c): Node {
        $var = (string)$c->ts->expect(Tok::NAME)->value;
        $c->ts->expect(Tok::OP, 'in');
        $iter = $this->expr->parse($c->ts);
        $c->ts->expect(Tok::TAG_END);

        $body = $c->subparse(fn(ParseContext $cx) => self::isTagAhead($cx->ts, ['else','endfor']));

        $elseBody = null;
        if (self::isTagAhead($c->ts, ['else'])) {
            $c->ts->expect(Tok::TAG_START);
            $c->ts->expect(Tok::NAME, 'else');
            $c->ts->expect(Tok::TAG_END);

            $elseBody = $c->subparse(fn(ParseContext $cx) => self::isTagAhead($cx->ts, ['endfor']));
        }

        $c->ts->expect(Tok::TAG_START);
        $c->ts->expect(Tok::NAME, 'endfor');
        $c->ts->expect(Tok::TAG_END);

        return new ForNode($var, $iter, $body, $elseBody);
    }

    private static function isTagAhead(TokenStream $ts, array $names): bool {
        if (!$ts->test(Tok::TAG_START)) return false;
        $la = $ts->la(1);
        return $la->type === Tok::NAME && in_array((string)$la->value, $names, true);
    }
}
