<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Lexer;

use Upside\Tpl\Core\Parser\ParserException;

/* -------------------------------------------------------------------------
 *  TokenStream
 * ------------------------------------------------------------------------- */

final class TokenStream {
    /** @param list<Token> $toks */
    public function __construct(
        private array $toks,
        private Source $src
    ) {}

    private int $pos = 0;

    public function cur(): Token { return $this->toks[$this->pos]; }
    public function next(): Token { $this->pos++; return $this->cur(); }
    public function la(int $n = 1): Token {
        return $this->toks[$this->pos + $n] ?? $this->toks[count($this->toks) - 1];
    }

    public function test(string $type, mixed $value = null): bool {
        $t = $this->cur();
        if ($t->type !== $type) return false;
        return $value === null ? true : $t->value === $value;
    }

    public function expect(string $type, mixed $value = null): Token {
        $t = $this->cur();
        if (!$this->test($type, $value)) {
            $need = $value === null ? $type : "{$type}(" . (string)$value . ")";
            throw new ParserException("Expected {$need}, got {$t->type}", $this->src, $t->span);
        }
        $this->next();
        return $t;
    }

    public function source(): Source { return $this->src; }
}
