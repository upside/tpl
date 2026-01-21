<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Tags;

use Upside\Tpl\Core\Parser\ParseContext;
use Upside\Tpl\MiniTwig\Expr\ExprParser;
use Upside\Tpl\MiniTwig\Lexer\Tok;
use Upside\Tpl\MiniTwig\Nodes\AssertNode;

final class AssertTag implements TagHandler
{
    public function __construct(private readonly ExprParser $expr) {}

    public function name(): string
    {
        return 'assert';
    }

    public function parse(ParseContext $c): AssertNode
    {
        $cond = $this->expr->parse($c->ts);
        $message = null;
        if ($c->ts->test(Tok::PUNCT, ',')) {
            $c->ts->next();
            $message = $this->expr->parse($c->ts);
        }
        $c->ts->expect(Tok::TAG_END);
        return new AssertNode($cond, $message);
    }
}
