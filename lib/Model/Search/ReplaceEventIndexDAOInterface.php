<?php

namespace Model\Search;
interface ReplaceEventIndexDAOInterface {

    /**
     * @param $idJob
     *
     * @return int
     */
    public function getActualIndex( $idJob ): int;

    /**
     * @param int $idJob
     * @param int $version
     *
     * @return mixed
     */
    public function save( int $id_job, int $version ): int;

    /**
     * @param $ttl
     *
     * @return mixed|void
     */
    public function setTtl( $ttl );
}