<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Diagnostics;

use Upside\Tpl\Core\Lexer\{Source, Span};

/* -------------------------------------------------------------------------
 *  Диагностика ошибок
 * ------------------------------------------------------------------------- */

class TplException extends \RuntimeException {
    public function __construct(
        string $message,
        public readonly ?Source $source = null,
        public readonly ?Span $span = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function pretty(): string {
        if (!$this->source || !$this->span) return $this->getMessage();
        return $this->getMessage() . "\n\n" . Diagnostic::render($this->source, $this->span);
    }
}
