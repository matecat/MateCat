<?php

class API_V2_ProjectCompletionStatus extends API_V2_ProtectedKleinController {

    private $project ;

    protected function afterConstruct() {

    }

    protected function validateRequest() {
        $project_id = $this->request->id_job;
        // check project has the correct id_customer
        $this->project = Projects_ProjectDao::findById( $project_id );
        if ( !$this->validateProjectAccess() ) {
            $this->response->code(404);
            $this->response->json(array('error' => 'This project does not exist'));
        }
    }

    private function validateProjectAccess() {
        return
            $this->validateProjectExists() &&
            $this->validateFeatureEnabled() &&
            $this->validateProjectInScope() ;
    }

    private function validateFeatureEnabled() {
        return $this->project->getOwnerFeature('project_completion') != false;
    }

    private function validateProjectExists() {
      return $this->project != false ;
    }

    private function validateProjectInScope() {
        return $this->api_record->getUser()->email == $this->project->id_customer ;
    }

    public function status() {
        // TODO: move this in to a json presenter class
        try {
            if ( $this->project->isMarkedComplete() ) {
                $completed = 'completed';
            } else  {
                $completed = 'not completed';
            }
        } catch ( Exception $e ){
            Log::doLog( $e->getMessage() ) ;
        }

        $this->response->json( array(
            'project_status' => $completed
        ) ) ;
    }
}
