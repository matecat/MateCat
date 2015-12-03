<?php
namespace API\V2  ;

class ProtectedKleinController extends KleinController {
    protected $api_key ;
    protected $api_secret ;
    protected $api_record ;

    private function validateAuth() {
        $headers = $this->request->headers();

        $this->api_key    = $headers['x-matecat-key'];
        $this->api_secret = $headers['x-matecat-secret'];

        if ( ! $this->validKeys() ) {
            $this->response->code(403);
            $this->response->json(array('error' => 'Authentication failed'));
        }

        $this->validateRequest();
    }

    private function validKeys() {
        if ($this->api_key && $this->api_secret) {
            $this->api_record = \ApiKeys_ApiKeyDao::findByKey( $this->api_key ) ;

            return $this->api_record &&
                $this->api_record->validSecret( $this->api_secret );
        }
        else {
            // TODO: Check a cookie to know if the request is coming from
            // MateCat itself.
        }

        return true;
    }

    public function respond($method) {
        $this->validateAuth();

        if (! $this->response->isLocked()) {
            $this->$method() ;
        }
    }

}
