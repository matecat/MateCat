<?php

namespace API\App;

use Klein\Response;
use RedisHandler;

trait RateLimiterTrait
{
    /**
     * @param Response $response
     * @param $email
     * @param $route
     * @throws \Exception
     */
    public function checkRateLimit(Response $response, $email, $route)
    {
        $maxRetries = 10;
        $key = $this->getKey($email, $route);
        $redis = $this->getRedis();

        if($redis->get($key) and $redis->get($key) > $maxRetries){
            $response->code(429);
            $response->header("Retry-After", $this->getTtl());
            exit();
        }
    }

    /**
     * @param $email
     * @param $route
     * @throws \Exception
     */
    public function incrementRateLimitCounter($email, $route)
    {
        $key = $this->getKey($email, $route);
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
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     */
    private function getRedis()
    {
        $redisHandler = new RedisHandler();

        return $redisHandler->getConnection();
    }

    /**
     * @param string $email
     * @param string $route
     * @return string
     */
    private function getKey($email, $route)
    {
        return md5($email.$route);
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