<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Parser;

final class ParserRegistry {
    /** @var array<int, list<StatementParser>> */
    private array $items = [];

    public function add(StatementParser $p, int $priority = 0): void {
        $this->items[$priority][] = $p;
        krsort($this->items);
    }

    /** @return list<StatementParser> */
    public function all(): array {
        $out = [];
        foreach ($this->items as $bucket) foreach ($bucket as $p) $out[] = $p;
        return $out;
    }
}
