<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Lexer;

/* -------------------------------------------------------------------------
 *  Токены языка
 * ------------------------------------------------------------------------- */

final class Tok
{
    public const TEXT = 'MT_TEXT';
    public const VAR_START = 'MT_VAR_START';
    public const VAR_END = 'MT_VAR_END';
    public const TAG_START = 'MT_TAG_START';
    public const TAG_END = 'MT_TAG_END';

    public const NAME = 'MT_NAME';
    public const NUMBER = 'MT_NUMBER';
    public const STRING = 'MT_STRING';
    public const OP = 'MT_OP';
    public const PUNCT = 'MT_PUNCT';
}
