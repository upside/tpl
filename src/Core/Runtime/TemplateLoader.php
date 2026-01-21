<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Runtime;

use Upside\Tpl\Core\Lexer\Source;

/* -------------------------------------------------------------------------
 *  Loader (для include/extends и т.п.)
 * ------------------------------------------------------------------------- */

interface TemplateLoader {
    public function load(string $name): Source;
}
