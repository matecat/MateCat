<?php


class createRandUserController extends ajaxController {

    public function __construct() {

        parent::__construct();

    }

    public function doAction() {
//        $tms                    = new TMSService( 1 );
        $tms                    = Engine::getInstance( 1 );
        $this->result[ 'data' ] = $tms->createMyMemoryKey();

    }

}
