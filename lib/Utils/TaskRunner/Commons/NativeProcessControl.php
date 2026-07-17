<?php

namespace Utils\TaskRunner\Commons;

class NativeProcessControl implements ProcessControlInterface
{
    public function fork(): int
    {
        return pcntl_fork();
    }

    public function kill(int $pid, int $signal): bool
    {
        return posix_kill($pid, $signal);
    }

    public function waitPid(int $pid, int &$status, int $flags): int
    {
        return pcntl_waitpid($pid, $status, $flags);
    }

    /** @param array<string> $args */
    public function exec(string $path, array $args): void
    {
        pcntl_exec($path, $args);
    }

    public function getHostname(): string
    {
        return gethostname() ?: '';
    }

    public function getPid(): int
    {
        return posix_getpid();
    }
}
