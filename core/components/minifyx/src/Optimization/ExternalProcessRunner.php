<?php

declare(strict_types=1);

namespace MinifyX\Optimization;

use Symfony\Component\Process\Process;

class ExternalProcessRunner
{
    private int $timeoutSeconds;
    private int $maxOutputBytes;
    private int $maxInputBytes;

    public function __construct(
        int $timeoutSeconds = 30,
        int $maxOutputBytes = 5_000_000,
        int $maxInputBytes = 5_000_000
    )
    {
        $this->timeoutSeconds = max(1, $timeoutSeconds);
        $this->maxOutputBytes = max(1024, $maxOutputBytes);
        $this->maxInputBytes = max(1024, $maxInputBytes);
    }

    public function isExecutableAvailable(string $binary): bool
    {
        $binary = trim($binary);
        if ($binary === '') {
            return false;
        }
        if (str_contains($binary, DIRECTORY_SEPARATOR) || str_contains($binary, '/')) {
            return is_file($binary) && is_executable($binary);
        }

        try {
            $process = new Process([$binary, '--version']);
            $process->setTimeout($this->timeoutSeconds);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param list<string> $command
     */
    public function run(array $command, string $input = ''): string
    {
        if (strlen($input) > $this->maxInputBytes) {
            throw new \RuntimeException('External optimizer input exceeds maximum allowed size.');
        }

        $process = new Process($command);
        $process->setTimeout($this->timeoutSeconds);
        $process->setInput($input);
        $process->run();

        if (!$process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());
            throw new \RuntimeException($stderr !== '' ? $stderr : 'External optimizer failed.');
        }

        $output = $process->getOutput();
        if (strlen($output) > $this->maxOutputBytes) {
            throw new \RuntimeException('External optimizer output exceeds maximum allowed size.');
        }

        return $output;
    }
}
