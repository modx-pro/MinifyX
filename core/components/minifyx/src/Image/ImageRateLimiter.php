<?php

declare(strict_types=1);

namespace MinifyX\Image;

final class ImageRateLimiter
{
    private string $stateDirectory;
    private int $maxRequests;
    private int $windowSeconds;

    /** @var callable */
    private $clock;

    /**
     * @param callable|null $clock function(): int
     */
    public function __construct(
        string $stateDirectory,
        int $maxRequests = 0,
        int $windowSeconds = 60,
        ?callable $clock = null
    ) {
        $this->stateDirectory = rtrim($stateDirectory, '/\\') . DIRECTORY_SEPARATOR;
        $this->maxRequests = max(0, $maxRequests);
        $this->windowSeconds = max(1, $windowSeconds);
        $this->clock = $clock ?? 'time';
    }

    public function isAllowed(string $clientHash): bool
    {
        if ($this->maxRequests === 0) {
            return true;
        }
        if (!is_dir($this->stateDirectory) && !@mkdir($this->stateDirectory, 0755, true)) {
            return false;
        }

        $file = $this->stateDirectory . hash('sha256', $clientHash) . '.json';
        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            return false;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }
            $now = (int) ($this->clock)();
            $raw = stream_get_contents($handle);
            $state = is_string($raw) ? json_decode($raw, true) : null;
            $startedAt = is_array($state) ? (int) ($state['startedAt'] ?? 0) : 0;
            $count = is_array($state) ? (int) ($state['count'] ?? 0) : 0;
            if ($startedAt <= 0 || $now - $startedAt >= $this->windowSeconds) {
                $startedAt = $now;
                $count = 0;
            }
            if ($count >= $this->maxRequests) {
                return false;
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, (string) json_encode(['startedAt' => $startedAt, 'count' => $count + 1]));
            fflush($handle);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
