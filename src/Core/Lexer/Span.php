<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Lexer;

final readonly class Span {
    public function __construct(
        public int $startOffset,
        public int $endOffset,
        public int $startLine,
        public int $startCol,
        public int $endLine,
        public int $endCol,
    ) {}
}
