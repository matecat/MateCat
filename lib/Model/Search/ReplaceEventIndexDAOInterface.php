<?php

interface Search_ReplaceEventIndexDAOInterface {

    /**
     * @param $idJob
     *
     * @return int
     */
    public function getActualIndex( $idJob);

    /**
     * @param $idJob
     * @param $version
     *
     * @return mixed
     */
    public function save($idJob, $version);

    /**
     * @param $ttl
     *
     * @return mixed|void
     */
    public function setTtl( $ttl );
}