<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;

class ApiKeyController extends KleinController {

    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
        //$this->appendValidator( new TeamAccessValidator( $this ) );
    }

    public function generate($user_id){

        //$apiKeyDao = new \ApiKeys_ApiKeyDao();

        // check if user already has a apikey

        // generate it

        // return it with secret
    }

    public function confirm($user_id){

        // set enabled to true

    }

    public function show($user_id){

        // does not show anymore api secret

    }

    public function delete($user_id){

        // check if user already has a apikey

        // delete it
    }
}