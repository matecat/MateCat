<?php

namespace Model\FilesStorage;

use DirectoryIterator;
use RecursiveIteratorIterator;

interface FilesystemAdapter
{
    public function copy(string $source, string $dest): bool;

    public function link(string $target, string $link): bool;

    public function mkdir(string $path, int $mode = 0755, bool $recursive = true): bool;

    public function unlink(string $path): bool;

    public function touch(string $path): bool;

    public function fileExists(string $path): bool;

    public function isDir(string $path): bool;

    public function isFile(string $path): bool;

    /**
     * @param string $path
     * @return string|false
     */
    public function fileGetContents(string $path): string|false;

    public function filePutContents(string $path, mixed $data): int|false;

    /**
     * @param string $path
     * @param int $flags
     * @return array<int, string>|false
     */
    public function file(string $path, int $flags = 0): array|false;

    /**
     * @return array<int, string>|false
     */
    public function scandir(string $path): array|false;

    public function deleteDir(string $path): bool;

    public function iterateDirectory(string $path): DirectoryIterator;

    /**
     * @return RecursiveIteratorIterator<\RecursiveDirectoryIterator>
     */
    public function iterateDirectoryRecursive(string $path, int $flags = 0): RecursiveIteratorIterator;
}
