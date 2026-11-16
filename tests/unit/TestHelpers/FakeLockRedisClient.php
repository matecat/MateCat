<?php

namespace Matecat\TestHelpers;

use Predis\Client;
use Predis\PredisException;
use Predis\Response\Status;

/**
 * An in-memory stand-in for the two commands RedisLock sends: SET ... NX to acquire, and the EVAL of
 * its compare-and-delete script to release, which this fake runs as the script would.
 *
 * Predis routes every command through __call(), so the Client cannot be mocked; extending it is the
 * way left. Expiry is not simulated: the tests read the TTL that was asked for from $setCalls.
 */
class FakeLockRedisClient extends Client
{
    /** @var array<string, mixed> */
    public array $keys = [];

    /** @var list<list<mixed>> */
    public array $setCalls = [];

    /** @var list<string> */
    public array $deleted = [];

    /** @var list<string> every command in the order it was sent, as "set <key>" or "del <key>" for a release */
    public array $log = [];

    /** Thrown by the release when set, standing in for Redis going away between acquire and release. */
    public ?PredisException $failOnDel = null;

    public function __construct()
    {
    }

    public function __call($commandID, $arguments): mixed
    {
        $command = strtolower($commandID);

        if ($command === 'set') {
            $key = (string)$arguments[0];
            $this->log[] = 'set ' . $key;
            $this->setCalls[] = $arguments;

            if (in_array('NX', $arguments, true) && array_key_exists($key, $this->keys)) {
                return null;
            }

            $this->keys[$key] = $arguments[1];

            return new Status('OK');
        }

        if ($command === 'eval') {
            [, , $key, $token] = $arguments;
            $this->log[] = 'del ' . $key;

            if ($this->failOnDel !== null) {
                throw $this->failOnDel;
            }

            if (($this->keys[$key] ?? null) !== $token) {
                return 0;
            }

            $this->deleted[] = $key;
            unset($this->keys[$key]);

            return 1;
        }

        throw new \LogicException("Unexpected Redis command in a lock test: $commandID");
    }
}
