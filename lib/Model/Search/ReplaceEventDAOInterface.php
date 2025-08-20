<?php

namespace Model\Search;

interface ReplaceEventDAOInterface {

    /**
     * @param int $id_job
     * @param int $version
     *
     * @return ReplaceEventStruct[]
     */
    public function getEvents( int $id_job, int $version ): array;

    /**
     * @param ReplaceEventStruct $eventStruct
     *
     * @return int
     */
    public function save( ReplaceEventStruct $eventStruct ): int;

    /**
     * @param $ttl
     *
     * @return mixed
     */
    public function setTtl( $ttl );
}