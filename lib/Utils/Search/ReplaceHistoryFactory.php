<?php

namespace Utils\Search;

use InvalidArgumentException;
use Model\Search\MySQLReplaceEventDAO;
use Model\Search\MySQLReplaceEventIndexDAO;
use Model\Search\RedisReplaceEventDAO;
use Model\Search\RedisReplaceEventIndexDAO;

class ReplaceHistoryFactory
{

    /**
     * @param $id_job
     * @param $driver
     * @param $ttl
     *
     * @return ReplaceHistory
     */
    public static function create($id_job, $driver, $ttl): ReplaceHistory
    {
        self::_checkDriver($driver);

        if ($driver === 'redis') {
            return new ReplaceHistory(
                    $id_job,
                    new RedisReplaceEventDAO(),
                    new RedisReplaceEventIndexDAO(),
                    $ttl
            );
        }

        return new ReplaceHistory(
                $id_job,
                new MySQLReplaceEventDAO(),
                new MySQLReplaceEventIndexDAO(),
                $ttl
        );
    }

    /**
     * @param string $driver
     */
    private static function _checkDriver(string $driver): void
    {
        $allowed_drivers = ['redis', 'mysql'];

        if (!in_array($driver, $allowed_drivers)) {
            throw new InvalidArgumentException($driver . ' is not an allowed driver ');
        }
    }
}