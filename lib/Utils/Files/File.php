<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 04/01/23
 * Time: 12:22
 *
 */

namespace Utils\Files;

use Model\FilesStorage\AbstractFilesStorage;

class File
{

    /**
     * @param string $filepath
     */
    public static function create(string $filepath): void
    {
        if (!self::exists($filepath)) {
            touch($filepath);
        }
    }

    /**
     * @param string $filepath
     */
    public static function delete(string $filepath): void
    {
        if (self::exists($filepath)) {
            unlink($filepath);
        }
    }

    /**
     * @param string $resource
     *
     * @return bool
     */
    public static function exists(string $resource): bool
    {
        return file_exists($resource);
    }

    /**
     * @param      $filepath
     * @param int  $options
     *
     * @return string|string[]
     */
    public static function info($filepath, int $options = 15): array|string
    {
        return AbstractFilesStorage::pathinfo_fix($filepath, $options);
    }
}