<?php

namespace Utils\TaskRunner\Commons;

interface ProcessControlInterface
{
    public function fork(): int;

    public function kill(int $pid, int $signal): bool;

    public function waitPid(int $pid, int &$status, int $flags): int;

    /** @param array<string> $args */
    public function exec(string $path, array $args): void;

    public function getHostname(): string;

    public function getPid(): int;
}
