<?php

use Predis\Client;
use Search\ReplaceEventStruct;

class Search_RedisReplaceEventDAO extends DataAccess_AbstractDao implements Search_ReplaceEventDAOInterface {

    const TABLE = 'replace_events';

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
     * @param $version
     *
     * @return ReplaceEventStruct[]
     */
    public function getEvents( $idJob, $version ) {
        $results = [];

        foreach ( $this->redis->hgetAll( $this->getRedisKey( $idJob, $version ) ) as $value ) {
            $results[] = unserialize( $value );
        }

        return $results;
    }

    /**
     * @param ReplaceEventStruct $eventStruct
     *
     * @return int
     */
    public function save( ReplaceEventStruct $eventStruct ) {
        // if not directly passed
        // try to assign the current version of the segment if it exists
        if ( null === $eventStruct->segment_version ) {
            $segment                      = ( new Translations_SegmentTranslationDao() )->getByJobId( $eventStruct->id_job )[ 0 ];
            $eventStruct->segment_version = $segment->version_number;
        }

        $eventStruct->created_at = date( 'Y-m-d H:i:s' );

        // insert
        $redisKey = $this->getRedisKey( $eventStruct->id_job, $eventStruct->replace_version );
        $index    = ( count( $this->getEvents( $eventStruct->id_job, $eventStruct->replace_version ) ) > 0 ) ? ( count( $this->getEvents( $eventStruct->id_job, $eventStruct->replace_version ) ) + 1 ) : 0;

        $result = $this->redis->hset( $redisKey, $index, serialize( $eventStruct ) );
        $this->redis->expire( $redisKey, $this->ttl );

        return $result ? 1 : 0;
    }

    /**
     * @param $idJob
     * @param $version
     *
     * @return string
     */
    private function getRedisKey( $idJob, $version ) {
        return md5( self::TABLE. '::' . $idJob . '::' . $version );
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
