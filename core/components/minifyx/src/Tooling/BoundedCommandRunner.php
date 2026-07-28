<?php

declare(strict_types=1);

namespace MinifyX\Tooling;

final class BoundedCommandRunner
{
    /** @var callable */
    private $capabilityCheck;
    /** @var callable|null */
    private $sequentialExecutor;
    private int $timeoutSeconds;
    /** @var callable */
    private $clock;

    public function __construct(
        ?callable $capabilityCheck = null,
        ?callable $sequentialExecutor = null,
        int $timeoutSeconds = 300,
        ?callable $clock = null
    ) {
        $this->capabilityCheck = $capabilityCheck ?? static function (): bool {
            return function_exists('proc_open');
        };
        $this->sequentialExecutor = $sequentialExecutor;
        $this->timeoutSeconds = max(1, $timeoutSeconds);
        $this->clock = $clock ?? static function (): float {
            return microtime(true);
        };
    }

    public function isSupported(): bool
    {
        return (bool) ($this->capabilityCheck)();
    }

    /**
     * @param list<list<string>> $commands
     * @return list<array{exitCode: int, stdout: string, stderr: string}>
     */
    public function run(array $commands, int $limit = 2): array
    {
        if (!$this->isSupported() || $limit < 2) {
            return $this->runSequential($commands);
        }

        $results = [];
        $running = [];
        $next = 0;
        while ($next < count($commands) || $running !== []) {
            while ($next < count($commands) && count($running) < $limit) {
                $process = $this->start($commands[$next]);
                if ($process === null) {
                    $results[$next] = ['exitCode' => 127, 'stdout' => '', 'stderr' => 'Could not start process.'];
                } else {
                    $running[$next] = $process;
                }
                $next++;
            }

            foreach (array_keys($running) as $index) {
                $this->drain($running[$index]);
                $process = $running[$index];
                $status = proc_get_status($process['resource']);
                if (!empty($status['running'])) {
                    if ($this->hasTimedOut($process)) {
                        $results[$index] = $this->terminate($process);
                        unset($running[$index]);
                    }
                    continue;
                }
                $results[$index] = $this->finish($process, (int) $status['exitcode']);
                unset($running[$index]);
            }
            if ($running !== []) {
                usleep(10000);
            }
        }
        ksort($results);

        return array_values($results);
    }

    /**
     * @param list<list<string>> $commands
     * @return list<array{exitCode: int, stdout: string, stderr: string}>
     */
    private function runSequential(array $commands): array
    {
        if (!$this->isSupported()) {
            if ($this->sequentialExecutor === null) {
                return array_map(static function (): array {
                    return ['exitCode' => 126, 'stdout' => '', 'stderr' => 'proc_open is unavailable.'];
                }, $commands);
            }

            return array_values(array_map($this->sequentialExecutor, $commands));
        }
        $results = [];
        foreach ($commands as $command) {
            $process = $this->start($command);
            if ($process === null) {
                $results[] = ['exitCode' => 127, 'stdout' => '', 'stderr' => 'Could not start process.'];
                continue;
            }
            $status = proc_get_status($process['resource']);
            while (!empty($status['running'])) {
                $this->drain($process);
                if ($this->hasTimedOut($process)) {
                    $results[] = $this->terminate($process);
                    continue 2;
                }
                usleep(10000);
                $status = proc_get_status($process['resource']);
            }
            $results[] = $this->finish($process, (int) $status['exitcode']);
        }

        return $results;
    }

    /**
     * @param list<string> $command
     * @return array{
     *   resource: resource,
     *   pipes: array<int, resource>,
     *   stdout: string,
     *   stderr: string,
     *   startedAt: float
     * }|null
     */
    private function start(array $command): ?array
    {
        $pipes = [];
        $resource = proc_open(
            $command,
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        if (!is_resource($resource)) {
            return null;
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return [
            'resource' => $resource,
            'pipes' => $pipes,
            'stdout' => '',
            'stderr' => '',
            'startedAt' => $this->now(),
        ];
    }

    /**
     * @param array{
     *   resource: resource,
     *   pipes: array<int, resource>,
     *   stdout: string,
     *   stderr: string,
     *   startedAt: float
     * } $process
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function finish(array $process, int $exitCode): array
    {
        $this->drain($process);
        fclose($process['pipes'][1]);
        fclose($process['pipes'][2]);
        $closedExitCode = proc_close($process['resource']);
        if ($exitCode < 0) {
            $exitCode = $closedExitCode;
        }

        return [
            'exitCode' => $exitCode,
            'stdout' => $process['stdout'],
            'stderr' => $process['stderr'],
        ];
    }

    /**
     * @param array{
     *   resource: resource,
     *   pipes: array<int, resource>,
     *   stdout: string,
     *   stderr: string,
     *   startedAt: float
     * } $process
     */
    private function drain(array &$process): void
    {
        $stdout = stream_get_contents($process['pipes'][1]);
        if (is_string($stdout)) {
            $process['stdout'] .= $stdout;
        }

        $stderr = stream_get_contents($process['pipes'][2]);
        if (is_string($stderr)) {
            $process['stderr'] .= $stderr;
        }
    }

    /**
     * @param array{
     *   resource: resource,
     *   pipes: array<int, resource>,
     *   stdout: string,
     *   stderr: string,
     *   startedAt: float
     * } $process
     */
    private function hasTimedOut(array $process): bool
    {
        return $this->now() - $process['startedAt'] >= $this->timeoutSeconds;
    }

    /**
     * @param array{
     *   resource: resource,
     *   pipes: array<int, resource>,
     *   stdout: string,
     *   stderr: string,
     *   startedAt: float
     * } $process
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function terminate(array $process): array
    {
        proc_terminate($process['resource']);
        usleep(100000);
        $status = proc_get_status($process['resource']);
        if (!empty($status['running'])) {
            proc_terminate($process['resource'], 9);
        }
        $process['stderr'] .= ($process['stderr'] === '' ? '' : "\n") . 'Process timed out.';

        return $this->finish($process, 124);
    }

    private function now(): float
    {
        return (float) ($this->clock)();
    }
}
