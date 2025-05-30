<?php

namespace API\V3;

use API\Commons\Exceptions\NotFoundException;
use API\V2\BaseChunkController;
use Files\MetadataDao as FileMetadataDao;
use Jobs\MetadataDao;
use Jobs_JobStruct;
use Projects_ProjectStruct;
use stdClass;

class MetaDataController extends BaseChunkController {

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
     * @param Projects_ProjectStruct $project
     *
     * @return stdClass
     */
    private function getProjectInfo( Projects_ProjectStruct $project ) {

        $metadata = new stdClass();

        foreach ( $project->getMetadata() as $metadatum ) {
            $key            = $metadatum->key;
            $metadata->$key = is_numeric($metadatum->getValue()) ? (int)$metadatum->getValue() : $metadatum->getValue();
        }

        return $metadata;
    }

    /**
     * @param Jobs_JobStruct $job
     *
     * @return stdClass
     */
    private function getJobMetaData( Jobs_JobStruct $job ): object {

        $metadata       = new stdClass();
        $jobMetaDataDao = new MetadataDao();

        foreach ( $jobMetaDataDao->getByJobIdAndPassword( $job->id, $job->password, 60 * 5 ) as $metadatum ) {
            $key            = $metadatum->key;
            $metadata->$key = is_numeric($metadatum->value) ? (int)$metadatum->value : $metadatum->value;
        }

        return $metadata;
    }

    /**
     * @param Jobs_JobStruct $job
     * @return array
     * @throws \ReflectionException
     */
    private function getJobFilesMetaData( Jobs_JobStruct $job ) {

        $metadata         = [];
        $filesMetaDataDao = new FileMetadataDao();

        foreach ( $job->getFiles() as $file ) {
            $metadatum = new stdClass();
            foreach ( $filesMetaDataDao->getByJobIdProjectAndIdFile( $job->getProject()->id, $file->id, 60 * 5 ) as $meta ) {
                $key             = $meta->key;
                $metadatum->$key = is_numeric($meta->value) ? (int)$meta->value : $meta->value;
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