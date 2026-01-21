<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Tags;

use Upside\Tpl\Core\AST\Node;
use Upside\Tpl\Core\Parser\{ParseContext, StatementParser};
use Upside\Tpl\MiniTwig\Lexer\Tok;
use Upside\Tpl\MiniTwig\Nodes\TextNode;

/* -------------------------------------------------------------------------
 *  Statement parsers: TEXT / {{ expr }}
 * ------------------------------------------------------------------------- */

final class TextStmtParser implements StatementParser {
    public function supports(ParseContext $c): bool { return $c->ts->test(Tok::TEXT); }
    public function parse(ParseContext $c): Node {
        $t = $c->ts->cur();
        $c->ts->next();
        return new TextNode((string)$t->value);
    }
}
