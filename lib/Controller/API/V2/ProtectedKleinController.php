<?php

class API_V2_ProtectedKleinController extends API_V2_KleinController {
    protected $auth_param ;

    private function validateAuth() {
        $this->auth_param = $this->request->headers()['x-matecat-auth'];
        $this->validateRequest();
    }

    public function respond($method) {
        $this->validateAuth();
        if (! $this->response->isLocked()) {
            $this->$method() ;
        }
    }

}
