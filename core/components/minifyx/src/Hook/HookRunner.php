<?php

declare(strict_types=1);

namespace MinifyX\Hook;

use MinifyX\Adapter\LegacyModxAdapter;
use MinifyX\Contract\HookHostInterface;
use MinifyX\Contract\ModxAdapterInterface;

final class HookRunner
{
    private ModxAdapterInterface $modx;
    private string $hooksPath;

    public function __construct(ModxAdapterInterface $modx, string $hooksPath)
    {
        $this->modx = $modx;
        $this->hooksPath = rtrim($hooksPath, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * @param list<string> $hooks
     */
    public function run(array $hooks, HookHostInterface $host): void
    {
        foreach ($hooks as $hook) {
            $hook = trim((string) $hook);
            if ($hook === '') {
                continue;
            }

            if (preg_match('#\.php$#', $hook)) {
                $path = $this->hooksPath . basename($hook);
                if (!is_file($path)) {
                    continue;
                }
                $modx = $this->modx instanceof LegacyModxAdapter
                    ? $this->modx->getRaw()
                    : null;
                $MinifyX = $host;
                include $path;
                continue;
            }

            $raw = $this->modx instanceof LegacyModxAdapter
                ? $this->modx->getRaw()
                : null;
            if ($raw !== null && method_exists($raw, 'runSnippet')) {
                $raw->runSnippet($hook, ['MinifyX' => $host]);
            } else {
                $this->modx->runSnippet($hook, ['MinifyX' => $host]);
            }
        }
    }
}
