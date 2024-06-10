<?php

use Predis\Client;

class Search_RedisReplaceEventIndexDAO extends DataAccess_AbstractDao implements Search_ReplaceEventIndexDAOInterface {

    const TABLE = 'replace_events_current_version';

    /**
     * @var Client
     */
    private $redis;

    /**
     * @var int
     */
    private $ttl = 10800; // 3 hours

    /**
     * Search_RedisReplaceEventDAO constructor.
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
    public function getActualIndex( $idJob ) {
        $index = $this->redis->get( $this->getRedisKey( $idJob ) );

        return ( null !== $index and $index > 0 ) ? (int)$index : 0;
    }

    /**
     * @param $idJob
     * @param $version
     *
     * @return int
     */
    public function save( $idJob, $version ) {
        $this->redis->set( $this->getRedisKey( $idJob ), $version );
        $this->redis->expire( $this->getRedisKey( $idJob ), $this->ttl );
    }

    /**
     * @param $idJob
     * @param $version
     *
     * @return string
     */
    private function getRedisKey( $idJob ) {
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
