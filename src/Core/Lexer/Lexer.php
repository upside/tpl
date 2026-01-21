<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Lexer;

final class Lexer {
    public function __construct(
        private readonly LexerState $s,
        private readonly RuleSet $rules
    ) {}

    /** @return list<Token> */
    public function tokenize(): array {
        $out = [];

        while (true) {
            if ($this->s->i >= $this->s->len()) {
                $m = $this->s->mark();
                $out[] = $this->s->makeToken($m, CoreTok::EOF, null);
                return $out;
            }

            $matched = false;

            foreach ($this->rules->forMode($this->s->mode()) as $r) {
                if (!$r->supports($this->s)) continue;

                $matched = true;
                $before = $this->s->i;

                $toks = $r->lex($this->s);
                foreach ($toks as $t) $out[] = $t;

                if ($this->s->i === $before && empty($toks)) {
                    $this->s->lexError("Lexer rule made no progress in mode={$this->s->mode()}");
                }
                break;
            }

            if (!$matched) {
                $near = $this->s->peek(20);
                $this->s->lexError("No lexer rule for mode={$this->s->mode()} near " . json_encode($near));
            }
        }
    }
}
