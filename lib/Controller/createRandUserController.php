<?php


class createRandUserController extends ajaxController {

    public function __construct() {

        parent::__construct();

    }

    public function doAction() {

        /**
         * @var $tms Engines_MyMemory
         */
        $tms                    = Engine::getInstance( 1 );
        $this->result[ 'data' ] = $tms->createMyMemoryKey();

    }

}