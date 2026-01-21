<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Lexer;

interface LexerRule
{
    public function supports(LexerState $s): bool;

    /** @return list<Token> */
    public function lex(LexerState $s): array;
}
