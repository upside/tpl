<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Runtime;

/* -------------------------------------------------------------------------
 *  File cache (compiled PHP)
 * ------------------------------------------------------------------------- */

final class FileTemplateCache {
    public function __construct(private readonly string $dir) {}

    public function pathFor(string $key): string {
        $a = substr($key, 0, 2);
        $b = substr($key, 2, 2);
        $d = rtrim($this->dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $a . DIRECTORY_SEPARATOR . $b;
        return $d . DIRECTORY_SEPARATOR . $key . '.php';
    }

    public function load(string $key): ?\Closure {
        $path = $this->pathFor($key);
        if (!is_file($path)) return null;

        $v = require $path;
        return $v instanceof \Closure ? $v : null;
    }

    public function store(string $key, string $php): \Closure {
        $path = $this->pathFor($key);
        $dir = dirname($path);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);

        $tmp = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        file_put_contents($tmp, $php, LOCK_EX);
        rename($tmp, $path);

        $v = require $path;
        if (!$v instanceof \Closure) {
            throw new \RuntimeException("Cache file did not return Closure: {$path}");
        }
        return $v;
    }
}
