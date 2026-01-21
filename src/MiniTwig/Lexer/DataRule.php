<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Lexer;

use Upside\Tpl\Core\Lexer\{LexerRule, LexerState, Token};

/* -------------------------------------------------------------------------
 *  Lexer rules (DataRule + CodeRule) — теперь без service locator:
 *  Syntax передаётся через конструктор.
 * ------------------------------------------------------------------------- */

final class DataRule implements LexerRule
{
    public function __construct(private readonly Syntax $syn) {}

    public function supports(LexerState $s): bool
    {
        return $s->mode() === 'DATA';
    }

    /** @return list<Token> */
    public function lex(LexerState $s): array
    {
        $syn = $this->syn;

        if ($s->startsWith($syn->comStart)) {
            $s->advanceBy(strlen($syn->comStart));
            $end = strpos($s->src->code, $syn->comEnd, $s->i);
            if ($end === false) $s->lexError("Unclosed comment");
            $s->advanceBy($end - $s->i);
            $s->advanceBy(strlen($syn->comEnd));
            return [];
        }

        if ($s->startsWith($syn->varStart)) {
            $m = $s->mark();
            $s->advanceBy(strlen($syn->varStart));
            $s->pushMode('VAR');
            return [$s->makeToken($m, Tok::VAR_START, $syn->varStart)];
        }

        if ($s->startsWith($syn->tagStart)) {
            $m = $s->mark();
            $s->advanceBy(strlen($syn->tagStart));
            $s->pushMode('TAG');
            return [$s->makeToken($m, Tok::TAG_START, $syn->tagStart)];
        }

        $m = $s->mark();
        $nexts = [];
        foreach ([$syn->varStart, $syn->tagStart, $syn->comStart] as $d) {
            $p = strpos($s->src->code, $d, $s->i);
            if ($p !== false) $nexts[] = $p;
        }
        $next = $nexts ? min($nexts) : $s->len();

        $text = substr($s->src->code, $s->i, $next - $s->i);
        $s->advanceBy(strlen($text));
        return [$s->makeToken($m, Tok::TEXT, $text)];
    }
}
