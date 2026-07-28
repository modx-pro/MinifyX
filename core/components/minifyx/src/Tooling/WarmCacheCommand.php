<?php

declare(strict_types=1);

namespace MinifyX\Tooling;

use MinifyX\Contract\HookHostInterface;
use MinifyX\Pipeline\AssetPipeline;

final class WarmCacheCommand implements HookHostInterface
{
    private AssetPipeline $pipeline;
    private string $content = '';
    private string $filename = '';

    public function __construct(AssetPipeline $pipeline)
    {
        $this->pipeline = $pipeline;
    }

    /**
     * @param array<string, array<int, string>> $groups
     * @param list<string> $selectedGroups
     * @return array{success: bool, groups: list<array<string, mixed>>, compilerCalls: int}
     */
    public function warm(array $groups, array $selectedGroups = []): array
    {
        $names = $selectedGroups === [] ? array_keys($groups) : $selectedGroups;
        $names = array_values(array_unique(array_map('strval', $names)));
        sort($names, SORT_STRING);
        $before = $this->pipeline->getCompilerCalls();
        $summary = [];
        $success = true;

        foreach ($names as $name) {
            if (!isset($groups[$name])) {
                $summary[] = ['group' => $name, 'success' => false, 'error' => 'Unknown group.'];
                $success = false;
                continue;
            }
            foreach ($this->splitByType($groups[$name]) as $type => $files) {
                if ($files === []) {
                    continue;
                }
                $result = $this->pipeline->processAndSave($files, $type, $this);
                $row = [
                    'group' => $name,
                    'type' => $type,
                    'success' => $result['success'],
                    'fromCache' => $result['fromCache'],
                    'filename' => $result['filename'],
                    'errorCode' => $result['errorCode'],
                ];
                $summary[] = $row;
                $success = $success && $result['success'];
            }
        }

        return [
            'success' => $success,
            'groups' => $summary,
            'compilerCalls' => $this->pipeline->getCompilerCalls() - $before,
        ];
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function isCss(?string $file = null): bool
    {
        return pathinfo($file ?? $this->filename, PATHINFO_EXTENSION) === 'css';
    }

    public function isJs(?string $file = null): bool
    {
        return pathinfo($file ?? $this->filename, PATHINFO_EXTENSION) === 'js';
    }

    /**
     * @param array<int, string> $files
     * @return array{css: list<string>, js: list<string>}
     */
    private function splitByType(array $files): array
    {
        $result = ['css' => [], 'js' => []];
        foreach ($files as $file) {
            $path = parse_url((string) $file, PHP_URL_PATH);
            $extension = strtolower(pathinfo(is_string($path) ? $path : (string) $file, PATHINFO_EXTENSION));
            $type = in_array($extension, ['css', 'scss', 'sass', 'less'], true) ? 'css' : 'js';
            $result[$type][] = (string) $file;
        }

        return $result;
    }
}
