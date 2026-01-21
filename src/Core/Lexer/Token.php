<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Lexer;

final readonly class Token {
    public function __construct(
        public string $type,
        public mixed $value,
        public Span $span,
    ) {}
}
