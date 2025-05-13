<?php

namespace API\App;

use DateTime;
use Exception;
use Klein\Response;
use Predis\Client;
use RedisHandler;

trait RateLimiterTrait {
    /**
     * @param Response $response
     * @param string   $identifier
     * @param string   $route
     * @param int      $maxRetries
     *
     * @return Response
     * @throws Exception
     */
    public function checkRateLimitResponse( Response $response, string $identifier, string $route, int $maxRetries = 10 ): ?Response {

        $key   = $this->getKey( $identifier, $route );
        $redis = $this->getRedis();

        if ( $redis->get( $key ) and $redis->get( $key ) > $maxRetries ) {
            $response->code( 429 );
            $response->header( "Retry-After", $redis->ttl( $key ) );

            // PENALTY: reset ttl
            $redis->expire( $key, $this->getTtl() );

            return $response;
        }

        return null;
    }

    /**
     * @param string $identifier
     * @param string $route
     *
     * @throws Exception
     */
    public function incrementRateLimitCounter( string $identifier, string $route ) {
        $key   = $this->getKey( $identifier, $route );
        $redis = $this->getRedis();

        if ( !$redis->get( $key ) ) {
            $redis->set( $key, 1 );
            $redis->expire( $key, $this->getTtl() );
        } else {
            $redis->incr( $key );
        }
    }

    /**
     * @return Client
     *
     * @throws Exception
     */
    private function getRedis(): Client {
        $redisHandler = new RedisHandler();

        return $redisHandler->getConnection();
    }

    /**
     * @param string $identifier
     * @param string $route
     *
     * @return string
     */
    private function getKey( string $identifier, string $route ): string {
        return md5( $identifier . $route );
    }

    /**
     * This function returns the end of the current minute + 1 minute (in seconds).
     *
     * Example:
     *
     * 12:30:46 ---> returns 14 + 60
     *
     * @return int
     */
    private function getTtl(): int {
        $date = new DateTime();
        $ttl  = 60 - $date->format( "s" );

        return 60 + $ttl;
    }
}