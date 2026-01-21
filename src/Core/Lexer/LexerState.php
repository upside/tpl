<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Lexer;

use Upside\Tpl\Core\Runtime\Engine;

/* -------------------------------------------------------------------------
 *  Lexer (rule-based + mode stack)
 * ------------------------------------------------------------------------- */

final class LexerState {
    public function __construct(
        public readonly Source $src,
        public readonly Engine $env,
    ) {}

    public int $i = 0;
    public int $line = 1;
    public int $col = 1;

    /** @var list<string> */
    private array $modes = ['DATA'];

    public function mode(): string { return $this->modes[count($this->modes) - 1]; }
    public function pushMode(string $m): void { $this->modes[] = $m; }
    public function popMode(): void { if (count($this->modes) > 1) array_pop($this->modes); }

    public function len(): int { return strlen($this->src->code); }

    public function peek(int $n = 1): string {
        if ($this->i >= $this->len()) return '';
        return substr($this->src->code, $this->i, $n);
    }

    public function startsWith(string $s): bool { return $this->peek(strlen($s)) === $s; }

    public function advance(): string {
        $c = $this->src->code[$this->i] ?? '';
        $this->i++;
        if ($c === "\n") { $this->line++; $this->col = 1; }
        else { $this->col++; }
        return $c;
    }

    public function advanceBy(int $n): void { for ($k=0; $k<$n; $k++) $this->advance(); }

    public function mark(): array { return [$this->i, $this->line, $this->col]; }

    public function makeToken(array $mark, string $type, mixed $value): Token {
        [$so,$sl,$sc] = $mark;
        $eo = $this->i; $el = $this->line; $ec = $this->col;
        return new Token($type, $value, new Span($so,$eo,$sl,$sc,$el,$ec));
    }

    public function lexError(string $message): never {
        $sp = new Span($this->i, $this->i + 1, $this->line, $this->col, $this->line, $this->col + 1);
        throw new LexerException($message, $this->src, $sp);
    }
}
