<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Nodes;

use Upside\Tpl\Core\AST\{Node, SequenceNode};

final readonly class BlockNode implements Node
{
    public function __construct(
        public string $name,
        public SequenceNode $body
    ) {}
}
