<?php

namespace API\V3;

use API\V2\Exceptions\NotFoundException;
use API\V2\KleinController;
use Jobs\MetadataDao;
use LQA\ChunkReviewDao;

class MetaDataController extends KleinController {

    public function index() {

        $result = [];

        // params
        $id_job   = $this->request->param( 'id_job' );
        $password = $this->request->param( 'password' );

        // find a job
        $job = $this->getJob( $id_job, $password );

        if ( null === $job ) {
            throw new NotFoundException( 'Job not found.' );
        }

        $metadata = new \stdClass();
        $metadata->project = $this->getProjectInfo( $job->getProject() );
        $metadata->job = $this->getJobMetaData( $job );
        $metadata->files = $this->getJobFilesMetaData( $job );

        $this->response->json( $metadata );
    }

    /**
     * @param $id_job
     * @param $password
     *
     * @return \Chunks_ChunkStruct|\DataAccess_IDaoStruct|\Jobs_JobStruct
     */
    private function getJob( $id_job, $password ) {

        $job = \Jobs_JobDao::getByIdAndPassword( $id_job, $password );

        if ( null === $job ) {
            $chunkReview = ChunkReviewDao::findByReviewPasswordAndJobId( $password, $id_job );
            if ( $chunkReview ) {
                $job = $chunkReview->getChunk();
            }
        }

        return $job;
    }

    /**
     * @param \Projects_ProjectStruct $project
     *
     * @return \stdClass
     */
    private function getProjectInfo( \Projects_ProjectStruct $project ) {

        $metadata = new \stdClass();

        foreach ( $project->getMetadata() as $metadatum ) {
            $key = $metadatum->key;
            $metadata->$key = $metadatum->value;
        }

        return $metadata;
    }

    /**
     * @param \Jobs_JobStruct $job
     *
     * @return \stdClass
     */
    private function getJobMetaData( \Jobs_JobStruct $job ) {

        $metadata = new \stdClass();
        $jobMetaDataDao = new MetadataDao();

        foreach ( $jobMetaDataDao->getByJobIdAndPassword( $job->id, $job->password ) as $metadatum ) {
            $key = $metadatum->key;
            $metadata->$key = $metadatum->value;
        }

        return $metadata;
    }

    /**
     * @param \Jobs_JobStruct $job
     *
     * @return array
     */
    private function getJobFilesMetaData( \Jobs_JobStruct $job ) {

        $metadata         = [];
        $filesMetaDataDao = new \Files\MetadataDao();

        foreach ( $job->getFiles() as $file ) {
            $metadatum = new \stdClass();
            foreach ( $filesMetaDataDao->getByJobIdProjectAndIdFile( $job->getProject()->id, $file->id ) as $meta ) {
                $key = $meta->key;
                $metadatum->$key = $meta->value;
            }

            $metadataObject = new \stdClass();
            $metadataObject->id = $file->id;
            $metadataObject->filename = $file->filename;
            $metadataObject->data = $metadatum;

            $metadata[] = $metadataObject;
        }

        return $metadata;
    }
}