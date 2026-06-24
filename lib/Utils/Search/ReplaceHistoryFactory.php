<?php

namespace Utils\Search;

use InvalidArgumentException;
use Model\Search\MySQLReplaceEventDao;
use Model\Search\MySQLReplaceEventIndexDao;
use Model\Search\RedisReplaceEventDao;
use Model\Search\RedisReplaceEventIndexDao;

class ReplaceHistoryFactory
{

    /**
     * @throws \Exception
     * @throws InvalidArgumentException
     */
    public static function create(int $id_job, string $driver, int $ttl): ReplaceHistory
    {
        self::_checkDriver($driver);

        if ($driver === 'redis') {
            return new ReplaceHistory(
                $id_job,
                new RedisReplaceEventDao(),
                new RedisReplaceEventIndexDao(),
                $ttl
            );
        }

        return new ReplaceHistory(
            $id_job,
            new MySQLReplaceEventDao(),
            new MySQLReplaceEventIndexDao(),
            $ttl
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function _checkDriver(string $driver): void
    {
        $allowed_drivers = ['redis', 'mysql'];

        if (!in_array($driver, $allowed_drivers)) {
            throw new InvalidArgumentException($driver . ' is not an allowed driver ');
        }
    }
}