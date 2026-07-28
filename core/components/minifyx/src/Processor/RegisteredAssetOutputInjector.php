<?php

declare(strict_types=1);

namespace MinifyX\Processor;

/**
 * Injects prepared asset tags into HTML without fragile exact str_replace pairs.
 */
final class RegisteredAssetOutputInjector
{
    /**
     * @param list<string> $headTags
     * @param list<string> $bodyTags
     */
    public function inject(
        string $html,
        string $registeredHead,
        string $registeredBody,
        array $headTags,
        array $bodyTags
    ): string {
        $headMarkup = implode("\n", array_filter($headTags, static fn (string $tag): bool => $tag !== ''));
        $bodyMarkup = implode("\n", array_filter($bodyTags, static fn (string $tag): bool => $tag !== ''));

        $html = $this->replaceSection($html, $registeredHead, 'head', $headMarkup);
        $html = $this->replaceSection($html, $registeredBody, 'body', $bodyMarkup);

        return $html;
    }

    private function replaceSection(string $html, string $block, string $tagName, string $markup): string
    {
        $replaced = false;
        $html = $this->replaceRegisteredBlock($html, $block, $markup, $replaced);
        if ($replaced || $markup === '') {
            return $html;
        }

        $pattern = '#</' . preg_quote($tagName, '#') . '\s*>#i';
        if (preg_match($pattern, $html)) {
            return $this->injectBeforeClosingTag($html, $pattern, $markup);
        }

        return $html . ($html === '' ? '' : "\n") . $markup;
    }

    private function replaceRegisteredBlock(
        string $html,
        string $block,
        string $replacement,
        bool &$replaced
    ): string {
        $replaced = false;
        if ($block === '') {
            return $html;
        }

        $candidate = $block;
        $normalized = trim($block);
        if (!str_contains($html, $candidate) && $normalized !== '' && str_contains($html, $normalized)) {
            $candidate = $normalized;
        }

        if (!str_contains($html, $candidate)) {
            return $html;
        }

        $replaced = true;

        return str_replace($candidate, $replacement, $html);
    }

    private function injectBeforeClosingTag(string $html, string $pattern, string $markup): string
    {
        return (string) preg_replace_callback(
            $pattern,
            static function (array $matches) use ($markup): string {
                return $markup . "\n" . $matches[0];
            },
            $html,
            1
        );
    }
}
