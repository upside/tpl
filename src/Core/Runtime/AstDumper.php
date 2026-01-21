<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Runtime;

/* -------------------------------------------------------------------------
 *  AST Dumper (отладка)
 * ------------------------------------------------------------------------- */

final class AstDumper {
    public static function dump(mixed $v, int $indent = 0, ?\SplObjectStorage $seen = null): string {
        $seen ??= new \SplObjectStorage();
        $pad = str_repeat('  ', $indent);

        if ($v === null) return $pad . "null";
        if (is_bool($v)) return $pad . ($v ? 'true' : 'false');
        if (is_int($v) || is_float($v)) return $pad . (string)$v;

        if (is_string($v)) {
            $s = str_replace(["\n","\r","\t"], ["\\n","\\r","\\t"], $v);
            if (strlen($s) > 140) $s = substr($s, 0, 140) . '…';
            return $pad . '"' . $s . '"';
        }

        if (is_array($v)) {
            if ($v === []) return $pad . "[]";
            $out = [$pad . "["];
            foreach ($v as $k => $vv) {
                $key = is_int($k) ? (string)$k : '"' . (string)$k . '"';
                $out[] = $pad . "  {$key} =>";
                $out[] = self::dump($vv, $indent + 2, $seen);
            }
            $out[] = $pad . "]";
            return implode("\n", $out);
        }

        if (is_object($v)) {
            if ($seen->offsetExists($v)) return $pad . get_class($v) . " { *recursion* }";
            $seen->offsetSet($v, true);

            $rc = new \ReflectionClass($v);
            $short = $rc->getShortName();
            $props = $rc->getProperties(\ReflectionProperty::IS_PUBLIC);

            if (!$props) return $pad . $short . " {}";

            $out = [$pad . $short . " {"];
            foreach ($props as $p) {
                $name = $p->getName();
                $val = '*unreadable*';
                try { $val = $p->getValue($v); } catch (\Throwable) {}
                $out[] = $pad . "  {$name}:";
                $out[] = self::dump($val, $indent + 2, $seen);
            }
            $out[] = $pad . "}";
            return implode("\n", $out);
        }

        return $pad . "*unknown*";
    }
}
