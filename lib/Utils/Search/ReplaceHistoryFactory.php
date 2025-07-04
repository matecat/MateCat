<?php

use Model\Search\MySQLReplaceEventDAO;
use Model\Search\MySQLReplaceEventIndexDAO;
use Model\Search\RedisReplaceEventDAO;
use Model\Search\RedisReplaceEventIndexDAO;

class Search_ReplaceHistoryFactory {

    /**
     * @param $id_job
     * @param $driver
     * @param $ttl
     *
     * @return Search_ReplaceHistory
     */
    public static function create( $id_job, $driver, $ttl ): Search_ReplaceHistory {
        self::_checkDriver( $driver );

        if ( $driver === 'redis' ) {
            return new Search_ReplaceHistory(
                    $id_job,
                    new RedisReplaceEventDAO(),
                    new RedisReplaceEventIndexDAO(),
                    $ttl
            );
        }

        return new Search_ReplaceHistory(
                $id_job,
                new MySQLReplaceEventDAO(),
                new MySQLReplaceEventIndexDAO(),
                $ttl
        );
    }

    /**
     * @param $driver
     */
    private static function _checkDriver( $driver ) {
        $allowed_drivers = [ 'redis', 'mysql' ];

        if ( !in_array( $driver, $allowed_drivers ) ) {
            throw new InvalidArgumentException( $driver . ' is not an allowed driver ' );
        }
    }
}