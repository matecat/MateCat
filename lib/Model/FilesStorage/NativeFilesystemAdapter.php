<?php

namespace Model\FilesStorage;

use DirectoryIterator;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Utils\Tools\Utils;

class NativeFilesystemAdapter implements FilesystemAdapter
{
    public function copy(string $source, string $dest): bool
    {
        return copy($source, $dest);
    }

    public function link(string $target, string $link): bool
    {
        return link($target, $link);
    }

    public function mkdir(string $path, int $mode = 0755, bool $recursive = true): bool
    {
        return mkdir($path, $mode, $recursive);
    }

    public function unlink(string $path): bool
    {
        return unlink($path);
    }

    public function touch(string $path): bool
    {
        return touch($path);
    }

    public function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    public function isDir(string $path): bool
    {
        return is_dir($path);
    }

    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    /**
     * @return string|false
     */
    public function fileGetContents(string $path): string|false
    {
        return file_get_contents($path);
    }

    public function filePutContents(string $path, mixed $data): int|false
    {
        return file_put_contents($path, $data);
    }

    /**
     * @param int-mask<FILE_USE_INCLUDE_PATH, FILE_IGNORE_NEW_LINES, FILE_SKIP_EMPTY_LINES, FILE_NO_DEFAULT_CONTEXT> $flags
     * @return array<int, string>|false
     */
    public function file(string $path, int $flags = 0): array|false
    {
        return file($path, $flags);
    }

    /**
     * @return array<int, string>|false
     */
    public function scandir(string $path): array|false
    {
        return scandir($path);
    }

    /**
     * @throws \Exception
     */
    public function deleteDir(string $path): bool
    {
        Utils::deleteDir($path);

        return true;
    }

    /**
     * @throws \RuntimeException
     */
    public function iterateDirectory(string $path): DirectoryIterator
    {
        return new DirectoryIterator($path);
    }

    /**
     * @return RecursiveIteratorIterator<RecursiveDirectoryIterator>
     * @throws \UnexpectedValueException
     */
    public function iterateDirectoryRecursive(string $path, int $flags = 0): RecursiveIteratorIterator
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS | $flags),
            RecursiveIteratorIterator::SELF_FIRST
        );
    }
}
