<?php

declare(strict_types=1);

namespace MinifyX\Legacy\CoffeeScript;

/**
 * Minimal legacy CoffeeScript subset compiler.
 *
 * Full CoffeeScript support is deprecated. Prefer precompiled JavaScript.
 * This adapter handles a tiny subset used by characterization fixtures.
 */
final class Compiler
{
    /**
     * @param array<string, mixed> $options
     */
    public static function compile(string $code, array $options = []): string
    {
        $filename = (string) ($options['filename'] ?? 'unknown.coffee');
        $lines = preg_split("/\r\n|\n|\r/", $code) ?: [];
        $js = [];
        $js[] = '/* MinifyX legacy CoffeeScript compile of ' . basename($filename) . ' */';

        foreach ($lines as $line) {
            $trimmed = rtrim($line);
            if ($trimmed === '' || str_starts_with(ltrim($trimmed), '#')) {
                continue;
            }

            // alert "hi" -> alert("hi");
            if (preg_match('/^(\s*)([A-Za-z_$][\w$]*)\s+(".*"|\'.*\')\s*$/', $trimmed, $m)) {
                $js[] = $m[1] . $m[2] . '(' . $m[3] . ');';
                continue;
            }

            // square = (x) -> x * x
            if (preg_match('/^(\s*)([A-Za-z_$][\w$]*)\s*=\s*\(([^)]*)\)\s*->\s*(.+)$/', $trimmed, $m)) {
                $js[] = $m[1] . 'var ' . $m[2] . ' = function(' . $m[3] . ') { return ' . $m[4] . '; };';
                continue;
            }

            // name = value
            if (preg_match('/^(\s*)([A-Za-z_$][\w$]*)\s*=\s*(.+)$/', $trimmed, $m)) {
                $js[] = $m[1] . 'var ' . $m[2] . ' = ' . $m[3] . ';';
                continue;
            }

            $needsSemicolon = str_ends_with($trimmed, ';')
                || str_ends_with($trimmed, '{')
                || str_ends_with($trimmed, '}');
            $js[] = $trimmed . ($needsSemicolon ? '' : ';');
        }

        return implode("\n", $js) . "\n";
    }
}
