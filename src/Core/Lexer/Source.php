<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Lexer;

/* -------------------------------------------------------------------------
 *  Source / Span / Token
 * ------------------------------------------------------------------------- */

final readonly class Source {
    public function __construct(
        public string $name,
        public string $code,
    ) {}
}
