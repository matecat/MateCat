<?php

class Search_RedisReplaceEventIndexDAO extends DataAccess_AbstractDao implements Search_ReplaceEventIndexDAOInterface {

    const TABLE = 'replace_events_current_version';

    /**
     * @var \Predis\Client
     */
    private $redis;

    /**
     * Search_RedisReplaceEventDAO constructor.
     *
     * @param null $con
     *
     * @throws ReflectionException
     * @throws \Predis\Connection\ConnectionException
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
        $index = $this->redis->get( $this->getRedisKey( $idJob ));

        return ( '' !== $index and $index > 0 ) ? $this->redis->get( $this->getRedisKey( $idJob ) ) : 0;
    }

    /**
     * @param $idJob
     * @param $version
     *
     * @return int
     */
    public function save( $idJob, $version ) {
        $this->redis->set( $this->getRedisKey( $idJob ), $version );
        $this->redis->expire( $this->getRedisKey( $idJob ), 60 * 60 * 3 );
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
}
