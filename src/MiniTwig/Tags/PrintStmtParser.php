<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Tags;

use Upside\Tpl\Core\AST\Node;
use Upside\Tpl\Core\Parser\{ParseContext, StatementParser};
use Upside\Tpl\MiniTwig\Expr\ExprParser;
use Upside\Tpl\MiniTwig\Lexer\Tok;
use Upside\Tpl\MiniTwig\Nodes\PrintNode;

final class PrintStmtParser implements StatementParser
{
    public function __construct(private readonly ExprParser $expr) {}

    public function supports(ParseContext $c): bool
    {
        return $c->ts->test(Tok::VAR_START);
    }

    public function parse(ParseContext $c): Node
    {
        $c->ts->next();
        $e = $this->expr->parse($c->ts);
        $c->ts->expect(Tok::VAR_END);
        return new PrintNode($e);
    }
}
