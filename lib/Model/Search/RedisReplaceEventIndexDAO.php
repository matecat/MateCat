<?php

namespace Model\Search;

use Model\DataAccess\AbstractDao;
use Predis\Client;
use ReflectionException;
use Utils\Redis\RedisHandler;

class RedisReplaceEventIndexDAO extends AbstractDao implements ReplaceEventIndexDAOInterface {

    const TABLE = 'replace_events_current_version';

    /**
     * @var Client
     */
    private Client $redis;

    /**
     * @var int
     */
    private int $ttl = 10800; // 3 hours

    /**
     * RedisReplaceEventDAO constructor.
     *
     * @param null $con
     *
     * @throws ReflectionException
     */
    public function __construct( $con = null ) {
        parent::__construct( $con );

        $this->redis = ( new RedisHandler() )->getConnection();
    }

    /**
     * @param $idJob
     *
     * @return int
     */
    public function getActualIndex( $idJob ): int {
        $index = $this->redis->get( $this->getRedisKey( $idJob ) );
        return ( null !== $index and $index > 0 ) ? (int)$index : 0;
    }

    /**
     * @param int $id_job
     * @param int $version
     *
     * @return int
     */
    public function save( int $id_job, int $version ): int {
        $this->redis->set( $this->getRedisKey( $id_job ), $version );
        $this->redis->expire( $this->getRedisKey( $id_job ), $this->ttl );
        return 1; // Redis doesn't return the number of affected rows, so we return 1 to indicate success
    }

    /**
     * @param $idJob
     * @param $version
     *
     * @return string
     */
    private function getRedisKey( $idJob ): string {
        return md5( self::TABLE . '::' . $idJob );
    }

    /**
     * @param $ttl
     *
     * @return void
     */
    public function setTtl( $ttl ) {
        $this->ttl = $ttl;
    }
}
