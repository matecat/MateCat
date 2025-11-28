<?php

namespace Model\Search;

interface ReplaceEventIndexDAOInterface
{

    /**
     * @param $idJob
     *
     * @return int
     */
    public function getActualIndex(int $idJob): int;

    /**
     * @param int $id_job
     * @param int $version
     *
     * @return mixed
     */
    public function save(int $id_job, int $version): int;

    /**
     * @param int $ttl
     *
     * @return void
     */
    public function setTtl(int $ttl): void;
}