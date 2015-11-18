<?php

class API_V2_ProjectDownload extends API_V2_ProtectedKleinController {

    private $project ;
    private $validator ;

    protected function afterConstruct() {
        $this->validator = new API_V2_ProjectValidator( $this->api_record );
    }

    protected function validateRequest() {
        if (
            !$this->validateFeatureEnabled('project_completion')  ||
            !($this->validator->validate( $this->request->id_project ) )
        ) {
            $this->response->code(404);
            $this->response->json( array('error' => 'This project does not exist') );
        }
    }

    private function validateFeatureEnabled() {
        return $this->project->getOwnerFeature('project_completion') != false;
    }

    public function download() {

    }

}
