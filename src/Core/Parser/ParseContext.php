<?php
declare(strict_types=1);

namespace Upside\Tpl\Core\Parser;

use Upside\Tpl\Core\AST\SequenceNode;
use Upside\Tpl\Core\Lexer\TokenStream;
use Upside\Tpl\Core\Runtime\Engine;

final class ParseContext {
    public function __construct(
        private readonly Parser $parser,
        public readonly TokenStream $ts,
        public readonly Engine $env
    ) {}

    /** @param callable(ParseContext):bool $stop */
    public function subparse(callable $stop): SequenceNode {
        return $this->parser->subparse($this, $stop);
    }
}
