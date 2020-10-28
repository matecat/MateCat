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

        $result[ 'metadata' ] = [
                'project' => $this->getProjectInfo( $job->getProject() ),   // project metadata
                'job'     => $this->getJobMetaData( $job ),                 // job metadata
                'files'   => $this->getJobFilesMetaData( $job ),            // job files metadata
        ];

        $this->response->json( $result );
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
     * @return |null
     */
    private function getProjectInfo( \Projects_ProjectStruct $project ) {

        $metadata = [];
        $projectMetadataDao = new \Projects_MetadataDao();

        foreach ( $projectMetadataDao->allByProjectId( (int)$project->id ) as $metadatum ) {
            $metadata[ $metadatum->key ] = $metadatum->value;
        }

        return $metadata;
    }

    /**
     * @param \Jobs_JobStruct $job
     *
     * @return array
     */
    private function getJobMetaData( \Jobs_JobStruct $job ) {

        $metadata = [];
        $jobMetaDataDao = new MetadataDao();

        foreach ( $jobMetaDataDao->getByJobIdAndPassword( $job->id, $job->password ) as $metadatum ) {
            $metadata[ $metadatum->key ] = $metadatum->value;
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
            $metadatum = [];
            foreach ( $filesMetaDataDao->getByJobIdProjectAndIdFile( $job->getProject()->id, $file->id ) as $meta ) {
                $metadatum[ $meta->key ] = $meta->value;
            }

            $metadata[] = [
                'id' => $file->id,
                'filename' => $file->filename,
                'data' => $metadatum
            ];
        }

        return $metadata;
    }
}