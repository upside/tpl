<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\AST;

final readonly class SequenceNode implements Node {
    /** @param list<Node> $nodes */
    public function __construct(public array $nodes) {}
}
