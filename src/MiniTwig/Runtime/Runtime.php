<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Runtime;

/* -------------------------------------------------------------------------
 *  Runtime (передаётся извне, хранится в Engine->shared('mt.rt'))
 * ------------------------------------------------------------------------- */

final class Runtime
{
    /** @var array<string, callable> */
    private array $functions = [];

    /** @var array<string, callable> */
    private array $filters = [];

    public function get(array $ctx, string $name): mixed
    {
        return $ctx[$name] ?? null;
    }

    public function getAttr(mixed $base, string $attr): mixed
    {
        if (is_array($base)) return $base[$attr] ?? null;
        if (is_object($base)) {
            if (isset($base->$attr)) return $base->$attr;
            $uc = ucfirst($attr);
            foreach (["get{$uc}", "is{$uc}"] as $m) {
                if (method_exists($base, $m)) return $base->$m();
            }
        }
        return null;
    }

    public function toIterable(mixed $v): iterable
    {
        if ($v === null) return [];
        if (is_array($v)) return $v;
        if ($v instanceof \Traversable) return $v;
        return [];
    }

    public function escape(mixed $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function debug(mixed $v): string
    {
        $dump = var_export($v, true);
        $safe = htmlspecialchars($dump, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<pre class="mt-debug">' . $safe . '</pre>';
    }

    public function assert(bool $cond, mixed $message = ''): void
    {
        if ($cond) return;
        $msg = (string)$message;
        if ($msg === '') $msg = 'Assertion failed';
        throw new \RuntimeException($msg);
    }

    public function addFunction(string $name, callable $fn): void
    {
        $this->functions[$name] = $fn;
    }

    public function addFilter(string $name, callable $fn): void
    {
        $this->filters[$name] = $fn;
    }

    public function callFunction(string $name, array $args): mixed
    {
        if (!array_key_exists($name, $this->functions)) {
            throw new \RuntimeException("Function not found: {$name}");
        }
        return ($this->functions[$name])(...$args);
    }

    public function applyFilter(string $name, mixed $value, array $args): mixed
    {
        if (!array_key_exists($name, $this->filters)) {
            throw new \RuntimeException("Filter not found: {$name}");
        }
        return ($this->filters[$name])($value, ...$args);
    }
}
