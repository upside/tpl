<?php
declare(strict_types=1);

namespace Upside\Tpl\MiniTwig\Lexer;

/* -------------------------------------------------------------------------
 *  Syntax config (инжектится в правила лексера)
 * ------------------------------------------------------------------------- */
final readonly class Syntax {
    public function __construct(
        public string $varStart = '{{',
        public string $varEnd   = '}}',
        public string $tagStart = '{%',
        public string $tagEnd   = '%}',
        public string $comStart = '{#',
        public string $comEnd   = '#}',

        /** @var list<string> */
        public array $keywordsAsOp = ['and','or','not','in'],

        /** @var list<string> */
        public array $multiOps = ['==','!=','<=','>='],

            /** @var list<string> */
            public array $singleOps = ['+','-','*','/','%','=','<','>','~','|'],

        /** @var list<string> */
        public array $punct = ['(',')','.',','],
    ) {}
}
