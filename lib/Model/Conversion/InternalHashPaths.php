<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 30/05/25
 * Time: 17:51
 *
 */

namespace Model\Conversion;

use DomainException;

class InternalHashPaths
{

    protected string $cacheHash;
    protected string $diskHash;

    /**
     * @param array $array_params Associative array with keys 'cacheHash' and 'diskHash'.
     */
    public function __construct(array $array_params)
    {
        if ($array_params != null) {
            foreach ($array_params as $property => $value) {
                $this->$property = $value;
            }
        }
    }

    /**
     * Returns the short SHA1 hash used to locate the converted file in the cache.
     *
     * @return string
     */
    public function getCacheHash(): string
    {
        return $this->cacheHash;
    }

    /**
     * Returns the full SHA1-based hash used to locate the original file on disk.
     *
     * @return string
     */
    public function getDiskHash(): string
    {
        return $this->diskHash;
    }

    /**
     * Returns true when neither hash has been set (i.e. conversion did not produce a cache entry).
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->cacheHash) && empty($this->diskHash);
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return void
     * @throws DomainException
     */
    public function __set(string $name, mixed $value): void
    {
        if (!property_exists($this, $name)) {
            throw new DomainException('Unknown property ' . $name);
        }
    }

}