<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Tags;

use Upside\Tpl\Core\AST\Node;
use Upside\Tpl\Core\Parser\ParseContext;
use Upside\Tpl\MiniTwig\Expr\ExprParser;
use Upside\Tpl\MiniTwig\Lexer\Tok;
use Upside\Tpl\MiniTwig\Nodes\IncludeNode;

final class IncludeTag implements TagHandler
{
    public function __construct(private readonly ExprParser $expr) {}

    public function name(): string
    {
        return 'include';
    }

    public function parse(ParseContext $c): Node
    {
        $tplExpr = $this->expr->parse($c->ts);
        $c->ts->expect(Tok::TAG_END);
        return new IncludeNode($tplExpr);
    }
}
