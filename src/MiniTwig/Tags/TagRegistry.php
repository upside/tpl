<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Tags;

final class TagRegistry
{
    /** @var array<string, TagHandler> */
    private array $map = [];

    public function add(TagHandler $h): void
    {
        $this->map[$h->name()] = $h;
    }

    public function get(string $name): ?TagHandler
    {
        return $this->map[$name] ?? null;
    }
}
