<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Tags;

use Upside\Tpl\Core\Lexer\{CoreTok, Token, TokenStream};
use Upside\Tpl\Core\Parser\{ParseContext, ParserException, StatementParser};
use Upside\Tpl\MiniTwig\Expr\ExprParser;
use Upside\Tpl\MiniTwig\Lexer\Tok;
use Upside\Tpl\MiniTwig\Nodes\{BlockNode, ExtendsNode, TextNode};

final class ExtendsStmtParser implements StatementParser
{
    public function __construct(private readonly ExprParser $expr) {}

    public function supports(ParseContext $c): bool
    {
        return $this->isExtendsAhead($c->ts);
    }

    public function parse(ParseContext $c): ExtendsNode
    {
        while ($this->isWhitespaceText($c->ts->cur())) {
            $c->ts->next();
        }

        $c->ts->expect(Tok::TAG_START);
        $c->ts->expect(Tok::NAME, 'extends');
        $tplExpr = $this->expr->parse($c->ts);
        $c->ts->expect(Tok::TAG_END);

        $body = $c->subparse(fn(ParseContext $cx) => $cx->ts->test(CoreTok::EOF));

        $blocks = [];
        foreach ($body->nodes as $node) {
            if ($node instanceof BlockNode) {
                $blocks[] = $node;
                continue;
            }
            if ($node instanceof TextNode && trim($node->text) === '') {
                continue;
            }
            $t = $c->ts->cur();
            throw new ParserException(
                "Templates that extend others may only contain blocks.",
                $c->ts->source(),
                $t->span
            );
        }

        return new ExtendsNode($tplExpr, $blocks);
    }

    private function isExtendsAhead(TokenStream $ts): bool
    {
        $offset = 0;
        while (true) {
            $t = $ts->la($offset);
            if ($this->isWhitespaceText($t)) {
                $offset++;
                continue;
            }
            if ($t->type !== Tok::TAG_START) return false;
            $t2 = $ts->la($offset + 1);
            return $t2->type === Tok::NAME && $t2->value === 'extends';
        }
    }

    private function isWhitespaceText(Token $t): bool
    {
        return $t->type === Tok::TEXT && trim((string)$t->value) === '';
    }
}
