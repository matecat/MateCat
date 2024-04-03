<?php

namespace API\V2;

use API\V2\Validators\JSONRequestValidator;
use API\V2\Validators\LoginValidator;

class UserController extends KleinController
{
    public function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
        $this->appendValidator( new JSONRequestValidator( $this ) );
    }

    public function edit(){

        $json = $this->request->body();
        $json = json_decode($json, true);

        $a = 3333;
        $a = 3333;
        $a = 3333;
        $a = 3333;
        $a = 3333;
        $a = 3333;



        return $this->response->json([
            'saa' => 'dffdsfds'
        ]);
    }
}