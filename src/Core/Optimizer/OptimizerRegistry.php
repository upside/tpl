<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Optimizer;

final class OptimizerRegistry {
    /** @var array<string, array{pass:OptimizerPass, priority:int}> */
    private array $passes = [];

    public function add(OptimizerPass $p, int $priority = 0): void {
        $this->passes[$p->id()] = ['pass'=>$p, 'priority'=>$priority];
    }

    /** @return list<OptimizerPass> */
    public function allSorted(): array {
        $list = array_values($this->passes);
        usort($list, function($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return strcmp($a['pass']->id(), $b['pass']->id());
            }
            return $b['priority'] <=> $a['priority'];
        });
        return array_map(fn($x) => $x['pass'], $list);
    }

    /** для сигнатуры кэша */
    public function signature(array $optionsById): string {
        $data = [];
        foreach ($this->allSorted() as $p) {
            $id = $p->id();
            $data[] = [
                'id' => $id,
                'ver' => $p->version(),
                'opt' => $optionsById[$id] ?? [],
            ];
        }
        return hash('xxh3', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
