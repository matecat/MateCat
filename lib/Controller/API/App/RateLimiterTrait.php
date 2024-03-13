<?php

namespace API\App;

use Klein\Response;
use RedisHandler;

trait RateLimiterTrait
{
    /**
     * @param Response $response
     * @param $identifier
     * @param $route
     * @return Response
     * @throws \Exception
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
     * @throws \Exception
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
     * @return \Predis\Client
     *
     * @throws \Exception
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
     * @throws \Exception
     */
    private function getTtl()
    {
        $date = new \DateTime();
        $ttl = 60 - $date->format("s");
        $ttl = 60 + $ttl;

        return $ttl;
    }
}