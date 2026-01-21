<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Lexer;

final class RuleSet {
    /** @var array<string, array<int, list<LexerRule>>> */
    private array $rules = [];

    public function add(string $mode, LexerRule $rule, int $priority = 0): void {
        $this->rules[$mode][$priority][] = $rule;
        krsort($this->rules[$mode]);
    }

    /** @return list<LexerRule> */
    public function forMode(string $mode): array {
        $out = [];
        foreach ($this->rules[$mode] ?? [] as $bucket) foreach ($bucket as $r) $out[] = $r;
        return $out;
    }
}
