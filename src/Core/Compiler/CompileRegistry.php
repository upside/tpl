<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Compiler;

final class CompileRegistry
{
    /** @var array<class-string, callable> */
    private array $map = [];

    public function add(string $class, callable $fn): void
    {
        $this->map[$class] = $fn;
    }

    public function get(object $x): callable
    {
        $cls = $x::class;
        $fn = $this->map[$cls] ?? null;
        if (!$fn) throw new \RuntimeException("No compiler for {$cls}");
        return $fn;
    }
}
