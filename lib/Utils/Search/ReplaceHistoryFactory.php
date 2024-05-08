<?php

class Search_ReplaceHistoryFactory {

    /**
     * @param $id_job
     * @param $driver
     * @param $ttl
     *
     * @return Search_ReplaceHistory
     */
    public static function create( $id_job, $driver, $ttl ) {
        self::_checkDriver( $driver );

        if ( $driver === 'redis' ) {
            return new Search_ReplaceHistory(
                    $id_job,
                    new Search_RedisReplaceEventDAO(),
                    new Search_RedisReplaceEventIndexDAO(),
                    $ttl
            );
        }

        return new Search_ReplaceHistory(
                $id_job,
                new Search_MySQLReplaceEventDAO(),
                new Search_MySQLReplaceEventIndexDAO(),
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