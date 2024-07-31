<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 31/07/24
 * Time: 16:16
 *
 */

namespace TaskRunner\Commons;

use Exception;
use RedisHandler;
use Throwable;

trait QueueCounterTrait {

    /**
     * @param RedisHandler $redisHandler
     * @param string       $key
     *
     * @return int
     * @throws Exception
     */
    protected function _decrement( RedisHandler $redisHandler, string $key ): int {

        try {

            $redisHandler->tryLock( $key, 60 );

            $decr = $redisHandler->getConnection()->decr( $key );
            if ( $decr < 0 ) {
                $redisHandler->getConnection()->set( $key, 0 );
                $decr = 0;
            }

        } finally {
            try {
                $redisHandler->unlock( $key );
            } catch ( Throwable $ignore ) {
            }
        }

        return $decr ?? 0;

    }

    /**
     * @param RedisHandler $redisHandler
     * @param string       $key
     *
     * @return int
     * @throws Exception
     */
    protected function _increment( RedisHandler $redisHandler, string $key ): int {

        $incr = 0;
        try {

            $redisHandler->tryLock( $key, 60 );

            $incr = $redisHandler->getConnection()->incr( $key );
            if ( $incr < 0 ) {
                $redisHandler->getConnection()->set( $key, 0 );
                $incr = 0;
            }

        } finally {
            try {
                $redisHandler->unlock( $key );
            } catch ( Throwable $ignore ) {
            }
        }

        return $incr;

    }

}