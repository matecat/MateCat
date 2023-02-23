<?php

class Chunks_ChunkDao extends DataAccess_AbstractDao {

    /**
     * @param     $id_job
     * @param     $password
     * @param int $ttl
     *
     * @return Chunks_ChunkStruct|DataAccess_IDaoStruct
     * @throws \Exceptions\NotFoundException
     */
    public static function getByIdAndPassword( $id_job, $password, $ttl = 0 ) {
        $fetched = Jobs_JobDao::getByIdAndPassword( $id_job, $password, $ttl, new Chunks_ChunkStruct );
        if ( empty( $fetched ) ) {
            throw new Exceptions\NotFoundException( 'Record not found' );
        } else {
            return $fetched;
        }
    }

    /**
     * @param     $id_job
     * @param     $password
     * @param int $ttl
     *
     * @return int
     */
    public static function getSegmentsCount($id_job, $password, $ttl = 0)
    {
        return Jobs_JobDao::getSegmentsCount( $id_job, $password, $ttl );
    }

    /**
     * @param Translations_SegmentTranslationStruct $translation
     * @param int                                   $ttl
     *
     * @return Chunks_ChunkStruct|DataAccess_IDaoStruct
     */
    public static function getBySegmentTranslation( Translations_SegmentTranslationStruct $translation, $ttl = 0 ) {
        return Jobs_JobDao::getBySegmentTranslation( $translation, $ttl, new Chunks_ChunkStruct() );
    }

    /**
     * @param     $id_job
     *
     * @param int $ttl
     *
     * @return Chunks_ChunkStruct[]|DataAccess_IDaoStruct[]
     */
    public static function getByJobID( $id_job, $ttl = 0 ) {
        return Jobs_JobDao::getById( $id_job, $ttl, new Chunks_ChunkStruct() );
    }

    /**
     * @param     $id_project
     * @param int $ttl
     *
     * @return Chunks_ChunkStruct[]|DataAccess_IDaoStruct[]
     */
    public function getByProjectID( $id_project, $ttl = 0 ) {
        return Jobs_JobDao::getByProjectId( $id_project, $ttl, new Chunks_ChunkStruct() );
    }

    /**
     * @param $id_project
     * @param $id_job
     * @param $ttl
     *
     * @return Chunks_ChunkStruct[]|DataAccess_IDaoStruct[]
     */
    public static function getByIdProjectAndIdJob( $id_project, $id_job, $ttl = 0 ) {
        return Jobs_JobDao::getByIdProjectAndIdJob( $id_project, $id_job, $ttl, new Chunks_ChunkStruct() );
    }

    /**
     * @param $id_job
     * @param $password
     * @param int $ttl
     * @return float|null
     */
    public static function getStandardWordCount($id_job, $password, $ttl = 86400)
    {
        return Jobs_JobDao::getStandardWordCount($id_job, $password, $ttl);
    }
}
