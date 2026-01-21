<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Nodes;

use Upside\Tpl\Core\AST\Node;

/* -------------------------------------------------------------------------
 *  AST nodes
 * ------------------------------------------------------------------------- */

final readonly class TextNode implements Node { public function __construct(public string $text) {} }
