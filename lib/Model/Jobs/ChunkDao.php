<?php

namespace Model\Jobs;

use Model\DataAccess\AbstractDao;
use Model\Exceptions\NotFoundException;
use ReflectionException;
use Translations_SegmentTranslationStruct;

class ChunkDao extends AbstractDao {

    /**
     * @param int    $id_job
     * @param string $password
     * @param int    $ttl
     *
     * @return JobStruct
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public static function getByIdAndPassword( int $id_job, string $password, int $ttl = 0 ): JobStruct {

        $fetched = JobDao::getByIdAndPassword( $id_job, $password, $ttl );

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
     * @return JobStruct
     * @throws ReflectionException
     */
    public static function getBySegmentTranslation( Translations_SegmentTranslationStruct $translation, int $ttl = 0 ): JobStruct {
        return JobDao::getBySegmentTranslation( $translation, $ttl );
    }

    /**
     * @param int $id_job
     *
     * @param int $ttl
     *
     * @return JobStruct[]
     * @throws ReflectionException
     */
    public static function getByJobID( int $id_job, int $ttl = 0 ): array {
        return JobDao::getById( $id_job, $ttl );
    }

    /**
     * @param int $id_project
     * @param int $ttl
     *
     * @return JobStruct[]
     * @throws ReflectionException
     */
    public function getByProjectID( int $id_project, int $ttl = 0 ): array {
        return JobDao::getByProjectId( $id_project, $ttl );
    }

    /**
     * @param int $id_project
     * @param int $id_job
     * @param int $ttl
     *
     * @return JobStruct[]
     * @throws ReflectionException
     */
    public static function getByIdProjectAndIdJob( int $id_project, int $id_job, int $ttl = 0 ): array {
        return JobDao::getByIdProjectAndIdJob( $id_project, $id_job, $ttl );
    }

}
