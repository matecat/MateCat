<?php

namespace API\V2 ;

use Features ;

class ProjectCompletionStatus extends ProtectedKleinController {

    private $validator ;

    protected function afterConstruct() {
    }

    protected function validateRequest() {
        $this->validator = new ProjectValidator(
            $this->api_record,
            $this->request->id_project
        );
        $this->validator->setFeature( 'project_completion' );

        $valid = $this->validator->validate();

        if (! $valid ) {
            $this->response->code(404);
            $this->response->json(
                array('error' => 'This project does not exist')
            );
        }
    }

    public function status() {
        // TODO: move this in to a json presenter class
        $uncompleted_reviews = \Projects_ProjectDao::uncompletedChunksByProjectId(
            $this->request->id_project,
            array('is_review' => true )
        );

        $uncompleted_translations = \Projects_ProjectDao::uncompletedChunksByProjectId(
            $this->request->id_project,
            array('is_review' => false )
        );

        $is_completed =
            count($uncompleted_translations) +
            count($uncompleted_reviews) == 0  ;

        $id_project = $this->request->id_project ;
        $response = array();
        $response = array('id' => $id_project );

        try {
            if ( $is_completed ) {
                $jobs = $this->validator->getProject()->getJobs();
                $response['jobs'] = array();

                foreach($jobs as $job) {
                    $response['jobs'][] = array(
                        'id' => $job->id,
                        'password' => $job->password,
                        'download_url' => \INIT::$HTTPHOST . "/?action=downloadFile" .
                            "&id_job=" .  $job->id .
                            "&password=" . $job->password

                    );
                }
                $response['completed'] = true;
            } else  {
                $response['completed'] = false;

                if ( count($uncompleted_reviews) > 0 ) {
                    $response['revise'] = array();
                    foreach($uncompleted_reviews as $chunk) {

                        $password = Features::filter(
                            'filter_job_password_to_review_password',
                            $this->validator->getProject()->id_customer,
                            $chunk->password,
                            $chunk->id
                        );

                        $response['revise'][] = array(
                            'id' => $chunk->id,
                            'password' => $password
                        );
                    }
                }

                if ( count($uncompleted_translations) > 0 ) {
                    $response['translate'] = array();
                    foreach($uncompleted_translations as $chunk) {
                        $response['translate'][] = array(
                            'id' => $chunk->id,
                            'password' => $chunk->password
                        );
                    }
                }
            }

            $this->response->json( array(
                'project_status' => $response
            ) ) ;

        } catch ( Exception $e ){
            Log::doLog( $e->getMessage() ) ;
            // TODO handle 500 response code here
        }

    }
}
