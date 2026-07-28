<?php

declare(strict_types=1);

namespace MinifyX\Processor;

use MinifyX\Support\SigningKeys;

/**
 * Rewrites <img> tags in a single pass using offset-based replacements.
 */
final class ImageRewriter
{
    private string $connector;
    private string $siteUrl;
    private string $defaultFilters;
    private string $excludePattern;
    /** @var list<string> */
    private array $signingKeys;

    /**
     * @param string|list<string> $signingKeys
     */
    public function __construct(
        string $connector,
        string $siteUrl,
        string $defaultFilters = 's[true]',
        string $excludePattern = '#(thumb|/\d+x\d+/)#i',
        $signingKeys = ''
    ) {
        $this->connector = $connector;
        $this->siteUrl = rtrim($siteUrl, '/');
        $this->defaultFilters = $defaultFilters;
        $this->excludePattern = $excludePattern;
        $this->signingKeys = SigningKeys::normalize($signingKeys);
    }

    public function rewrite(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        $result = '';
        $offset = 0;
        if (!preg_match_all('/<img\b[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        foreach ($matches[0] as [$tag, $pos]) {
            $result .= substr($html, $offset, $pos - $offset);
            $result .= $this->rewriteTag($tag);
            $offset = $pos + strlen($tag);
        }
        $result .= substr($html, $offset);

        return $result;
    }

    private function rewriteTag(string $tag): string
    {
        if ($this->excludePattern !== '' && @preg_match($this->excludePattern, $tag) === 1) {
            return $tag;
        }

        $propertyPattern = '/(src|height|width|filters)\s*=\s*[\'"]([^\'"]*)[\'"]/i';
        if (!preg_match_all($propertyPattern, $tag, $properties, PREG_SET_ORDER)) {
            return $tag;
        }

        $src = null;
        $width = null;
        $height = null;
        $filters = null;
        foreach ($properties as $property) {
            $name = strtolower($property[1]);
            $value = $property[2];
            if ($name === 'src') {
                $src = $value;
            } elseif ($name === 'width') {
                $width = $value;
            } elseif ($name === 'height') {
                $height = $value;
            } elseif ($name === 'filters') {
                $filters = $value;
            }
        }

        if ($src === null || ($width === null && $height === null && $filters === null)) {
            return $tag;
        }

        if (str_contains($src, '://')) {
            if (!str_starts_with($src, $this->siteUrl)) {
                return $tag;
            }
            $src = substr($src, strlen($this->siteUrl));
        }

        $file = ltrim($src, '/');
        $resize = '';
        if ($width !== null) {
            $resize .= 'w[' . $width . ']';
        }
        if ($height !== null) {
            $resize .= 'h[' . $height . ']';
        }
        $resize .= $filters ?? $this->defaultFilters;

        $newSrc = $this->connector . '?files=' . rawurlencode($file) . '&resize=' . rawurlencode($resize);
        if ($this->signingKeys !== []) {
            $sig = hash_hmac('sha256', $file . '|' . $resize, $this->signingKeys[0]);
            $newSrc .= '&sig=' . rawurlencode($sig);
        }

        $srcReplacement = ' src="' . htmlspecialchars($newSrc, ENT_QUOTES) . '"';
        $updated = preg_replace('/\ssrc\s*=\s*[\'"][^\'"]*[\'"]/i', $srcReplacement, $tag, 1);
        if ($filters !== null) {
            $updated = preg_replace('/\sfilters\s*=\s*[\'"][^\'"]*[\'"]/i', '', (string) $updated, 1);
        }

        return is_string($updated) ? $updated : $tag;
    }

}
