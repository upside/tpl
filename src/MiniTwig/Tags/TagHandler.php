<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Tags;

use Upside\Tpl\Core\AST\Node;
use Upside\Tpl\Core\Parser\ParseContext;

/* -------------------------------------------------------------------------
 *  Tag system
 * ------------------------------------------------------------------------- */

interface TagHandler
{
    public function name(): string;

    public function parse(ParseContext $c): Node;
}
