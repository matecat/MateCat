<?php

namespace Utils\Redis;

use Predis\Client;
use Random\RandomException;

/**
 * A lock held in one Redis key, released only by the holder that took it.
 *
 * Acquiring is a single SET NX with a TTL, so the key can never exist without an expiry. Its value
 * is a random token handed back to the holder, and releasing compares that token and deletes the
 * key in one script. A holder that outlived its TTL, whose key has since expired and been taken by
 * somebody else, deletes nothing instead of the next holder's lock.
 *
 * It does not fence: a holder past its TTL does not know it lost the lock and carries on. The token
 * only stops it from releasing what it no longer owns, so the TTL must stay above the longest run.
 */
class RedisLock
{
    private const string RELEASE_SCRIPT = <<<'LUA'
        if redis.call('GET', KEYS[1]) == ARGV[1] then
            return redis.call('DEL', KEYS[1])
        end
        return 0
        LUA;

    public function __construct(private readonly Client $redis)
    {
    }

    /**
     * @param string $key
     * @param int $ttlSeconds
     * @return string|null the token that releases the lock, or null while another holder has it
     *
     * @throws RandomException when no random source is available for the token
     */
    public function acquire(string $key, int $ttlSeconds): ?string
    {
        $token = bin2hex(random_bytes(16));

        if (!$this->redis->set($key, $token, 'EX', $ttlSeconds, 'NX')) {
            return null;
        }

        return $token;
    }

    /**
     * @param string $key
     * @param string $token
     * @return bool true when the key still held $token and is gone; false when it had expired, or
     *              expired and was taken by another holder, whose lock stays in place
     *
     */
    public function release(string $key, string $token): bool
    {
        return (int)$this->redis->eval(self::RELEASE_SCRIPT, 1, $key, $token) === 1;
    }
}
