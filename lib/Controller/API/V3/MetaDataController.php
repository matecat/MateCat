<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Model\Files\MetadataDao as FileMetadataDao;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Model\Projects\ProjectStruct;
use ReflectionException;
use stdClass;
use Utils\Tools\Utils;

class MetaDataController extends KleinController {
    use ChunkNotFoundHandlerTrait;

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function index() {

        // params
        $id_job   = $this->request->param( 'id_job' );
        $password = $this->request->param( 'password' );

        // find a job
        $job = $this->getJob( $id_job, $password );

        if ( null === $job ) {
            throw new NotFoundException( 'Job not found.' );
        }

        $this->chunk = $job;
        $this->return404IfTheJobWasDeleted();

        $metadata          = new stdClass();
        $metadata->project = $this->getProjectInfo( $job->getProject() );
        $metadata->job     = $this->getJobMetaData( $job );
        $metadata->files   = $this->getJobFilesMetaData( $job );

        $this->response->json( $metadata );
    }

    /**
     * @param ProjectStruct $project
     *
     * @return stdClass
     */
    private function getProjectInfo( ProjectStruct $project ): stdClass {

        $metadata = new stdClass();
        $metadata->mt_extra = new stdClass();

        $myExtraKeys = [
                'pre_translate_files',
                'mmt_glossaries',
                'mmt_pre_import_tm',
                'mmt_activate_context_analyzer',
                'mmt_glossaries_case_sensitive_matching',
                'lara_glossaries',
                'deepl_formality',
                'deepl_id_glossary',
                'deepl_engine_type',
                'intento_routing',
                'intento_provider',
        ];

        foreach ( $project->getMetadata() as $metadatum ) {

            $key = $metadatum->key;

            if ( in_array( $key, $myExtraKeys ) ) {
                $metadata->mt_extra->$key = Utils::formatStringValue( $metadatum->value );
            } else {
                $metadata->$key = Utils::formatStringValue( $metadatum->value );
            }
        }

        return $metadata;
    }

    /**
     * @param \Model\Jobs\JobStruct $job
     *
     * @return stdClass
     * @throws ReflectionException
     */
    private function getJobMetaData( JobStruct $job ): object {

        $metadata       = new stdClass();
        $jobMetaDataDao = new MetadataDao();

        foreach ( $jobMetaDataDao->getByJobIdAndPassword( $job->id, $job->password, 60 * 5 ) as $metadatum ) {
            $key            = $metadatum->key;
            $metadata->$key = Utils::formatStringValue( $metadatum->value );
        }

        if( !property_exists($metadata,MetadataDao::SUBFILTERING_HANDLERS) ){
            $metadata->{MetadataDao::SUBFILTERING_HANDLERS} = [];
        }

        return $metadata;
    }

    /**
     * @param \Model\Jobs\JobStruct $job
     *
     * @return array
     * @throws ReflectionException
     */
    private function getJobFilesMetaData( JobStruct $job ): array {

        $metadata         = [];
        $filesMetaDataDao = new FileMetadataDao();

        foreach ( $job->getFiles() as $file ) {
            $metadatum = new stdClass();
            foreach ( $filesMetaDataDao->getByJobIdProjectAndIdFile( $job->getProject()->id, $file->id, 60 * 5 ) as $meta ) {
                $key             = $meta->key;
                $metadatum->$key = Utils::formatStringValue( $meta->value );
            }

            $metadataObject           = new stdClass();
            $metadataObject->id       = $file->id;
            $metadataObject->filename = $file->filename;
            $metadataObject->data     = $metadatum;

            $metadata[] = $metadataObject;
        }

        return $metadata;
    }
}