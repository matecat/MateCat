<?php

namespace API\App;

use DateTime;
use Exception;
use Klein\Response;
use Predis\Client;
use Predis\PredisException;
use RedisHandler;

trait RateLimiterTrait
{
    /**
     * @param Response $response
     * @param          $identifier
     * @param          $route
     * @param int      $maxRetries
     *
     * @return Response
     * @throws PredisException
     * @throws Exception
     */
    public function checkRateLimitResponse(Response $response, $identifier, $route, $maxRetries = 10)
    {

        $key = $this->getKey($identifier, $route);
        $redis = $this->getRedis();

        if($redis->get($key) and $redis->get($key) > $maxRetries){
            $response->code(429);
            $response->header("Retry-After", $redis->ttl($key));

            // PENALTY: reset ttl
            $redis->expire($key, $this->getTtl());

            return $response;
        }

        return null;
    }

    /**
     * @param $identifier
     * @param $route
     * @throws Exception
     */
    public function incrementRateLimitCounter($identifier, $route)
    {
        $key = $this->getKey($identifier, $route);
        $redis = $this->getRedis();

        if(!$redis->get($key)){
            $redis->set($key, 1);
            $redis->expire($key, $this->getTtl());
        } else {
            $redis->incr($key);
        }
    }

    /**
     * @return Client
     *
     * @throws Exception
     */
    private function getRedis()
    {
        $redisHandler = new RedisHandler();

        return $redisHandler->getConnection();
    }

    /**
     * @param string $identifier
     * @param string $route
     * @return string
     */
    private function getKey($identifier, $route)
    {
        return md5($identifier.$route);
    }

    /**
     * This function returns the end of current minute + 1 minute (in seconds).
     *
     * Example:
     *
     * 12:30:46 ---> returns 14 + 60
     *
     * @return int|string
     */
    private function getTtl()
    {
        $date = new DateTime();
        $ttl = 60 - $date->format("s");

        return 60 + $ttl;
    }
}