<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Parser;

use Upside\Tpl\Core\AST\Node;

/* -------------------------------------------------------------------------
 *  Parser framework (recursive descent + subparse)
 * ------------------------------------------------------------------------- */

interface StatementParser {
    public function supports(ParseContext $c): bool;
    public function parse(ParseContext $c): Node;
}
