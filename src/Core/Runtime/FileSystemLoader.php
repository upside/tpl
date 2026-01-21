<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Runtime;

use Upside\Tpl\Core\Lexer\Source;

final class FileSystemLoader implements TemplateLoader {
    public function __construct(
        private readonly string $baseDir,
        private readonly string $defaultSuffix = ''
    ) {}

    public function load(string $name): Source {
        $path = $this->resolvePath($name);
        $code = @file_get_contents($path);
        if ($code === false) {
            throw new \RuntimeException("Template not found or unreadable: {$name}");
        }
        return new Source($name, $code);
    }

    private function resolvePath(string $name): string {
        $n = str_replace(["\0", "\\", "//"], ['', '/', '/'], $name);
        $n = ltrim($n, '/');

        $parts = explode('/', $n);
        foreach ($parts as $p) {
            if ($p === '..') throw new \RuntimeException("Invalid template name (path traversal): {$name}");
        }

        if ($this->defaultSuffix !== '' && !str_ends_with($n, $this->defaultSuffix)) {
            $n .= $this->defaultSuffix;
        }

        $base = rtrim($this->baseDir, DIRECTORY_SEPARATOR);
        return $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $n);
    }
}
