<?php

namespace Model\Search;

use Model\DataAccess\AbstractDao;
use Model\Translations\SegmentTranslationDao;
use Predis\Client;
use ReflectionException;
use Utils\Redis\RedisHandler;

class RedisReplaceEventDAO extends AbstractDao implements ReplaceEventDAOInterface {

    const string TABLE = 'replace_events';

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
     * @param int $id_job
     * @param int $version
     *
     * @return ReplaceEventStruct[]
     */
    public function getEvents( int $id_job, int $version ): array {
        $results = [];

        foreach ( $this->redis->hgetAll( $this->getRedisKey( $id_job, $version ) ) as $value ) {
            $results[] = unserialize( $value );
        }

        return $results;
    }

    /**
     * @param ReplaceEventStruct $eventStruct
     *
     * @return int
     */
    public function save( ReplaceEventStruct $eventStruct ): int {
        // if not directly passed
        // try to assign the current version of the segment if it exists
        if ( null === $eventStruct->segment_version ) {
            $segment                      = ( new SegmentTranslationDao() )->getByJobId( $eventStruct->id_job )[ 0 ];
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
     * @param int $idJob
     * @param int $version
     *
     * @return string
     */
    private function getRedisKey( int $idJob, int $version ): string {
        return md5( self::TABLE . '::' . $idJob . '::' . $version );
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
