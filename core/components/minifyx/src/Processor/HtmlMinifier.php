<?php

declare(strict_types=1);

namespace MinifyX\Processor;

/**
 * Context-aware HTML minifier that preserves pre/textarea/script/style/conditional comments.
 */
final class HtmlMinifier
{
    public function minify(string $html): string
    {
        $tokens = [];
        $index = 0;
        $protected = preg_replace_callback(
            '#<(pre|textarea|script|style)\b[^>]*>.*?</\1>|<!--\[if.*?<!\[endif\]-->|<\?php.*?\?>#is',
            static function (array $matches) use (&$tokens, &$index): string {
                $key = '___MINIFYX_TOKEN_' . $index . '___';
                $tokens[$key] = $matches[0];
                $index++;

                return $key;
            },
            $html
        );

        if ($protected === null) {
            return $html;
        }

        $protected = preg_replace('/<!--(?!\[).*?-->/s', '', $protected) ?? $protected;
        $protected = preg_replace('/\r\n|\r|\n|\t/', ' ', $protected) ?? $protected;
        $protected = preg_replace('/>\s+</', '><', $protected) ?? $protected;
        $protected = preg_replace('/ {2,}/', ' ', $protected) ?? $protected;
        $protected = trim($protected);

        return strtr($protected, $tokens);
    }
}
