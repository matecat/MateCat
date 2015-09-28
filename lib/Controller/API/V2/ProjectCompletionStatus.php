<?php

class API_V2_ProjectCompletionStatus extends API_V2_ProtectedKleinController {

    private $project ;
    private $validator ;

    protected function afterConstruct() {
    }

    protected function validateRequest() {
        $this->validator = new API_V2_ProjectValidator(
            $this->api_record,
            $this->request->id_project
        );
        $this->validator->setFeature( 'project_completion' );

        if (! ($this->validator->validate() )) {
            $this->response->code(404);
            $this->response->json( array('error' => 'This project does not exist') );
        }
    }

    public function status() {
        // TODO: move this in to a json presenter class
        $uncompleted = Projects_ProjectDao::uncompletedChunksByProjectId(
            $this->request->id_project
        );

        $is_completed = count($uncompleted) == 0 ;

        $id_project = $this->request->id_project ;
        $response = array();
        $response = array('id' => $id_project );

        try {
            if ( $is_completed ) {
                $response['completed'] = false;
                $response['chunks'] = array();

                foreach($uncompleted as $chunk) {
                    $response['chunks'][] = array(
                        'id' => $chunk->id,
                        'password' => $chunk->password
                    );
                }

            } else  {
                $jobs = $this->validator->getProject()->getJobs();
                $response['jobs'] = array();

                foreach($jobs as $job) {
                    $response['jobs'][] = array(
                        'id' => $job->id,
                        'password' => $job->password,
                        'download_url' => 'http://' . $_SERVER['HTTP_HOST'] . "/?action=downloadFile" .
                            "&id_job=" .  $job->id .
                            "&password=" . $job->password  .
                            "&filename=foobar.txt"

                    );
                }

                $response['completed'] = true;
            }

        } catch ( Exception $e ){
            Log::doLog( $e->getMessage() ) ;
        }

        $this->response->json( array(
            'project_status' => $response
        ) ) ;
    }
}
