<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\IR\Optimizer;

/**
 * Registry for IR optimization passes.
 *
 * Allows adding passes with priorities and retrieving them in sorted order.
 * Also computes a signature for caching purposes.
 */
final class IrOptimizerRegistry
{
    /**
     * @var array<string, array{pass: IrOptimizerPass, priority: int}>
     */
    private array $passes = [];

    public function add(IrOptimizerPass $pass, int $priority = 0): void
    {
        $this->passes[$pass->id()] = ['pass' => $pass, 'priority' => $priority];
    }

    /**
     * @return list<IrOptimizerPass>
     */
    public function allSorted(): array
    {
        $list = array_values($this->passes);
        usort($list, function ($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return strcmp($a['pass']->id(), $b['pass']->id());
            }
            return $b['priority'] <=> $a['priority'];
        });
        return array_map(fn($x) => $x['pass'], $list);
    }

    /**
     * Compute a cache signature based on registered passes and options.
     *
     * @param array<string, mixed> $optionsById
     * @return string
     */
    public function signature(array $optionsById): string
    {
        $data = [];
        foreach ($this->allSorted() as $pass) {
            $id = $pass->id();
            $data[] = [
                'id'  => $id,
                'ver' => $pass->version(),
                'opt' => $optionsById[$id] ?? [],
            ];
        }
        return hash('xxh3', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
