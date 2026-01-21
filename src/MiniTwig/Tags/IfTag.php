<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Tags;

use Upside\Tpl\Core\AST\Node;
use Upside\Tpl\Core\Lexer\TokenStream;
use Upside\Tpl\Core\Parser\ParseContext;
use Upside\Tpl\MiniTwig\Expr\ExprParser;
use Upside\Tpl\MiniTwig\Lexer\Tok;
use Upside\Tpl\MiniTwig\Nodes\IfNode;

/* -------------------------------------------------------------------------
 *  Tags: if / for / include (каждый получает ExprParser через конструктор)
 * ------------------------------------------------------------------------- */

final class IfTag implements TagHandler
{
    public function __construct(private readonly ExprParser $expr) {}

    public function name(): string
    {
        return 'if';
    }

    public function parse(ParseContext $c): Node
    {
        $cond = $this->expr->parse($c->ts);
        $c->ts->expect(Tok::TAG_END);

        $branches = [];
        $body = $c->subparse(fn(ParseContext $cx) => self::isTagAhead($cx->ts, ['elseif', 'else', 'endif']));
        $branches[] = ['cond' => $cond, 'body' => $body];

        while (self::isTagAhead($c->ts, ['elseif'])) {
            $c->ts->expect(Tok::TAG_START);
            $c->ts->expect(Tok::NAME, 'elseif');

            $cc = $this->expr->parse($c->ts);
            $c->ts->expect(Tok::TAG_END);

            $bb = $c->subparse(fn(ParseContext $cx) => self::isTagAhead($cx->ts, ['elseif', 'else', 'endif']));
            $branches[] = ['cond' => $cc, 'body' => $bb];
        }

        $elseBody = null;
        if (self::isTagAhead($c->ts, ['else'])) {
            $c->ts->expect(Tok::TAG_START);
            $c->ts->expect(Tok::NAME, 'else');
            $c->ts->expect(Tok::TAG_END);

            $elseBody = $c->subparse(fn(ParseContext $cx) => self::isTagAhead($cx->ts, ['endif']));
        }

        $c->ts->expect(Tok::TAG_START);
        $c->ts->expect(Tok::NAME, 'endif');
        $c->ts->expect(Tok::TAG_END);

        return new IfNode($branches, $elseBody);
    }

    private static function isTagAhead(TokenStream $ts, array $names): bool
    {
        if (!$ts->test(Tok::TAG_START)) return false;
        $la = $ts->la(1);
        return $la->type === Tok::NAME && in_array((string)$la->value, $names, true);
    }
}
