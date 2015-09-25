<?php

class API_V2_ProtectedKleinController extends API_V2_KleinController {
    protected $api_key ;
    protected $api_secret ;
    protected $api_record ;

    private function validateAuth() {
        $this->api_key    = $this->request->headers()['x-matecat-key'];
        $this->api_secret = $this->request->headers()['x-matecat-secret'];

        if ( ! $this->validKeys() ) {
            $this->response->code(403);
            $this->response->json(array('error' => 'Authentication failed'));
        }

        $this->validateRequest();
    }

    private function validKeys() {
      $this->api_record = ApiKeys_ApiKeyDao::findByKey( $this->api_key ) ;

      return $this->api_record &&
        $this->api_record->validSecret( $this->api_secret );
    }

    public function respond($method) {
        $this->validateAuth();

        if (! $this->response->isLocked()) {
            $this->$method() ;
        }
    }

}
