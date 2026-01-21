<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Expr;

use Upside\Tpl\Core\AST\Expr;
use Upside\Tpl\Core\Lexer\TokenStream;
use Upside\Tpl\Core\Parser\ParserException;
use Upside\Tpl\MiniTwig\Lexer\Tok;

final class ExprParser
{
    public function __construct(private readonly OperatorTable $ops) {}

    public function parse(TokenStream $ts, int $minBp = 0): Expr
    {
        $left = $this->prefix($ts);

        while (true) {
            if ($ts->test(Tok::PUNCT, '.')) {
                $ts->next();
                $attrTok = $ts->expect(Tok::NAME);
                $left = new GetAttrExpr($left, (string)$attrTok->value);
                continue;
            }

            if ($ts->test(Tok::PUNCT, '(')) {
                $left = $this->parseCall($ts, $left);
                continue;
            }

            if (!$ts->test(Tok::OP)) break;
            $op = (string)$ts->cur()->value;
            $meta = $this->ops->bin($op);
            if (!$meta) break;

            if ($meta['bp'] < $minBp) break;

            $ts->next();
            if ($op === '|') {
                $left = $this->parseFilter($ts, $left);
                continue;
            }
            $nextMin = $meta['right'] ? $meta['bp'] : $meta['bp'] + 1;
            $right = $this->parse($ts, $nextMin);

            $left = new BinaryExpr($left, $op, $right);
        }

        return $left;
    }

    private function parseCall(TokenStream $ts, Expr $callee): Expr
    {
        $ts->expect(Tok::PUNCT, '(');
        $args = [];
        if (!$ts->test(Tok::PUNCT, ')')) {
            $args[] = $this->parse($ts, 0);
            while ($ts->test(Tok::PUNCT, ',')) {
                $ts->next();
                $args[] = $this->parse($ts, 0);
            }
        }
        $ts->expect(Tok::PUNCT, ')');
        return new CallExpr($callee, $args);
    }

    private function parseFilter(TokenStream $ts, Expr $input): Expr
    {
        $nameTok = $ts->expect(Tok::NAME);
        $args = [];
        if ($ts->test(Tok::PUNCT, '(')) {
            $args = $this->parseCallArgs($ts);
        }
        return new FilterExpr($input, (string)$nameTok->value, $args);
    }

    /** @return list<Expr> */
    private function parseCallArgs(TokenStream $ts): array
    {
        $ts->expect(Tok::PUNCT, '(');
        $args = [];
        if (!$ts->test(Tok::PUNCT, ')')) {
            $args[] = $this->parse($ts, 0);
            while ($ts->test(Tok::PUNCT, ',')) {
                $ts->next();
                $args[] = $this->parse($ts, 0);
            }
        }
        $ts->expect(Tok::PUNCT, ')');
        return $args;
    }

    private function prefix(TokenStream $ts): Expr
    {
        $t = $ts->cur();

        if ($t->type === Tok::NUMBER) {
            $ts->next();
            return new LitExpr($t->value);
        }
        if ($t->type === Tok::STRING) {
            $ts->next();
            return new LitExpr($t->value);
        }

        if ($t->type === Tok::NAME) {
            $name = (string)$t->value;
            if ($name === 'parent' && $ts->la(1)->type === Tok::PUNCT && $ts->la(1)->value === '('
                && $ts->la(2)->type === Tok::PUNCT && $ts->la(2)->value === ')') {
                $ts->next();
                $ts->expect(Tok::PUNCT, '(');
                $ts->expect(Tok::PUNCT, ')');
                return new ParentExpr();
            }
            $ts->next();
            return match ($name) {
                'true'  => new LitExpr(true),
                'false' => new LitExpr(false),
                'null'  => new LitExpr(null),
                default => new NameExpr($name),
            };
        }

        if ($t->type === Tok::OP) {
            $op = (string)$t->value;
            $meta = $this->ops->pre($op);
            if ($meta) {
                $ts->next();
                $e = $this->parse($ts, $meta['bp']);
                return new UnaryExpr($op, $e);
            }
        }

        if ($ts->test(Tok::PUNCT, '(')) {
            $ts->next();
            $e = $this->parse($ts, 0);
            $ts->expect(Tok::PUNCT, ')');
            return $e;
        }

        throw new ParserException("Unexpected token in expression: {$t->type}", $ts->source(), $t->span);
    }
}
