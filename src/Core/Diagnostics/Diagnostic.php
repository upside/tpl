<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Diagnostics;

use Upside\Tpl\Core\Lexer\{Source, Span};

final class Diagnostic {
    public static function render(Source $src, Span $sp, int $contextLines = 2): string {
        $lines = preg_split("/\r\n|\n|\r/", $src->code) ?: [''];
        $lineIdx = max(1, $sp->startLine);

        $start = max(1, $lineIdx - $contextLines);
        $end   = min(count($lines), $lineIdx + $contextLines);

        $pad = strlen((string)$end);
        $out = [];

        $out[] = "Template: {$src->name}";
        $out[] = "At: line {$sp->startLine}, col {$sp->startCol}";
        $out[] = "";

        for ($ln = $start; $ln <= $end; $ln++) {
            $num = str_pad((string)$ln, $pad, ' ', STR_PAD_LEFT);
            $text = $lines[$ln - 1] ?? '';
            $out[] = "{$num} | {$text}";
            if ($ln === $sp->startLine) {
                $caretPad = str_repeat(' ', $pad) . " | ";
                $caret = str_repeat(' ', max(0, $sp->startCol - 1)) . "^";
                $out[] = $caretPad . $caret;
            }
        }

        return implode("\n", $out);
    }
}
