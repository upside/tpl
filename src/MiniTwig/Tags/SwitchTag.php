<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Tags;

use Upside\Tpl\Core\Lexer\TokenStream;
use Upside\Tpl\Core\Parser\ParseContext;
use Upside\Tpl\MiniTwig\Expr\ExprParser;
use Upside\Tpl\MiniTwig\Lexer\Tok;
use Upside\Tpl\MiniTwig\Nodes\SwitchNode;

final class SwitchTag implements TagHandler
{
    public function __construct(private readonly ExprParser $expr) {}

    public function name(): string { return 'switch'; }

    public function parse(ParseContext $c): SwitchNode
    {
        $expr = $this->expr->parse($c->ts);
        $c->ts->expect(Tok::TAG_END);

        $cases = [];
        $default = null;

        while (true) {
            $c->ts->expect(Tok::TAG_START);
            $name = (string)$c->ts->expect(Tok::NAME)->value;

            if ($name === 'case') {
                $caseExpr = $this->expr->parse($c->ts);
                $c->ts->expect(Tok::TAG_END);
                $body = $c->subparse(fn(ParseContext $cx) => self::isTagAhead($cx->ts, ['case','default','endswitch']));
                $cases[] = ['expr' => $caseExpr, 'body' => $body];
                continue;
            }

            if ($name === 'default') {
                $c->ts->expect(Tok::TAG_END);
                $default = $c->subparse(fn(ParseContext $cx) => self::isTagAhead($cx->ts, ['endswitch']));
                continue;
            }

            if ($name === 'endswitch') {
                $c->ts->expect(Tok::TAG_END);
                break;
            }
        }

        return new SwitchNode($expr, $cases, $default);
    }

    private static function isTagAhead(TokenStream $ts, array $names): bool
    {
        if (!$ts->test(Tok::TAG_START)) return false;
        $la = $ts->la(1);
        return $la->type === Tok::NAME && in_array((string)$la->value, $names, true);
    }
}
