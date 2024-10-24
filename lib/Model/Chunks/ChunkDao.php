<?php

use Exceptions\NotFoundException;

class Chunks_ChunkDao extends DataAccess_AbstractDao {

    /**
     * @param int    $id_job
     * @param string $password
     * @param int    $ttl
     *
     * @return Jobs_JobStruct
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public static function getByIdAndPassword( int $id_job, string $password, int $ttl = 0 ): Jobs_JobStruct {

        $fetched = Jobs_JobDao::getByIdAndPassword( $id_job, $password, $ttl );

        if ( empty( $fetched ) ) {
            throw new NotFoundException( 'Job not found' );
        } else {
            return $fetched;
        }

    }

    /**
     * @param Translations_SegmentTranslationStruct $translation
     * @param int                                   $ttl
     *
     * @return Jobs_JobStruct
     * @throws ReflectionException
     */
    public static function getBySegmentTranslation( Translations_SegmentTranslationStruct $translation, int $ttl = 0 ): Jobs_JobStruct {
        return Jobs_JobDao::getBySegmentTranslation( $translation, $ttl );
    }

    /**
     * @param int $id_job
     *
     * @param int $ttl
     *
     * @return Jobs_JobStruct[]
     * @throws ReflectionException
     */
    public static function getByJobID( int $id_job, int $ttl = 0 ): array {
        return Jobs_JobDao::getById( $id_job, $ttl );
    }

    /**
     * @param int $id_project
     * @param int $ttl
     *
     * @return Jobs_JobStruct[]
     * @throws ReflectionException
     */
    public function getByProjectID( int $id_project, int $ttl = 0 ): array {
        return Jobs_JobDao::getByProjectId( $id_project, $ttl );
    }

    /**
     * @param int $id_project
     * @param int $id_job
     * @param int $ttl
     *
     * @return Jobs_JobStruct[]
     */
    public static function getByIdProjectAndIdJob( int $id_project, int $id_job, int $ttl = 0 ): array {
        return Jobs_JobDao::getByIdProjectAndIdJob( $id_project, $id_job, $ttl );
    }

}
