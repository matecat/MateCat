<?php

use Search\ReplaceEventStruct;

interface Search_ReplaceEventDAOInterface {

    /**
     * @param $idJob
     * @param $version
     *
     * @return ReplaceEventStruct[]
     */
    public function getEvents($idJob, $version);

    /**
     * @param ReplaceEventStruct $eventStruct
     *
     * @return int
     */
    public function save(ReplaceEventStruct $eventStruct);

    /**
     * @param $ttl
     *
     * @return mixed
     */
    public function setTtl($ttl);
}