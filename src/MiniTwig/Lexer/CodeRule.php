<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Lexer;

use Upside\Tpl\Core\Lexer\{LexerRule, LexerState, Token};

final class CodeRule implements LexerRule {
    public function __construct(private readonly Syntax $syn) {}

    public function supports(LexerState $s): bool {
        return $s->mode() === 'VAR' || $s->mode() === 'TAG';
    }

    /** @return list<Token> */
    public function lex(LexerState $s): array {
        $syn = $this->syn;

        if ($s->mode() === 'VAR' && $s->startsWith($syn->varEnd)) {
            $m = $s->mark();
            $s->advanceBy(strlen($syn->varEnd));
            $s->popMode();
            return [$s->makeToken($m, Tok::VAR_END, $syn->varEnd)];
        }

        if ($s->mode() === 'TAG' && $s->startsWith($syn->tagEnd)) {
            $m = $s->mark();
            $s->advanceBy(strlen($syn->tagEnd));
            $s->popMode();
            return [$s->makeToken($m, Tok::TAG_END, $syn->tagEnd)];
        }

        while (true) {
            $c = $s->peek(1);
            if ($c === ' ' || $c === "\t" || $c === "\n" || $c === "\r") $s->advance();
            else break;
        }

        if ($s->mode() === 'VAR' && $s->startsWith($syn->varEnd)) {
            $m = $s->mark();
            $s->advanceBy(strlen($syn->varEnd));
            $s->popMode();
            return [$s->makeToken($m, Tok::VAR_END, $syn->varEnd)];
        }

        if ($s->mode() === 'TAG' && $s->startsWith($syn->tagEnd)) {
            $m = $s->mark();
            $s->advanceBy(strlen($syn->tagEnd));
            $s->popMode();
            return [$s->makeToken($m, Tok::TAG_END, $syn->tagEnd)];
        }

        $c = $s->peek(1);

        if ($c === '"' || $c === "'") {
            $m = $s->mark();
            $q = $s->advance();
            $buf = '';
            while (true) {
                $ch = $s->peek(1);
                if ($ch === '') $s->lexError("Unclosed string");
                $ch = $s->advance();
                if ($ch === $q) break;
                if ($ch === '\\') {
                    $n = $s->advance();
                    $buf .= match ($n) {
                        'n' => "\n", 'r' => "\r", 't' => "\t",
                        '\\' => '\\', '"' => '"', "'" => "'",
                        default => $n,
                    };
                    continue;
                }
                $buf .= $ch;
            }
            return [$s->makeToken($m, Tok::STRING, $buf)];
        }

        if ($c !== '' && ctype_digit($c)) {
            $m = $s->mark();
            $buf = '';
            while (true) {
                $ch = $s->peek(1);
                if ($ch !== '' && (ctype_digit($ch) || $ch === '.')) $buf .= $s->advance();
                else break;
            }
            $v = str_contains($buf, '.') ? (float)$buf : (int)$buf;
            return [$s->makeToken($m, Tok::NUMBER, $v)];
        }

        if ($c !== '' && (ctype_alpha($c) || $c === '_')) {
            $m = $s->mark();
            $buf = '';
            while (true) {
                $ch = $s->peek(1);
                if ($ch !== '' && (ctype_alnum($ch) || $ch === '_')) $buf .= $s->advance();
                else break;
            }
            if (in_array($buf, $syn->keywordsAsOp, true)) {
                return [$s->makeToken($m, Tok::OP, $buf)];
            }
            return [$s->makeToken($m, Tok::NAME, $buf)];
        }

        foreach ($syn->multiOps as $op) {
            if ($s->startsWith($op)) {
                $m = $s->mark();
                $s->advanceBy(strlen($op));
                return [$s->makeToken($m, Tok::OP, $op)];
            }
        }

        if (in_array($c, $syn->singleOps, true)) {
            $m = $s->mark();
            $s->advance();
            return [$s->makeToken($m, Tok::OP, $c)];
        }

        if (in_array($c, $syn->punct, true)) {
            $m = $s->mark();
            $s->advance();
            return [$s->makeToken($m, Tok::PUNCT, $c)];
        }

        $s->lexError("Unexpected char in code: " . json_encode($c));
    }
}
