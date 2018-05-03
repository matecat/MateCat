<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 01/09/2017
 * Time: 18:32
 */

namespace Features\Dqf\Service;


use Features\Dqf\Service\Struct\IBaseStruct;
use INIT;

class GenericSession extends Session implements ISession {

    protected $userEmail;

    public function __construct( $email ) {
        $this->userEmail  = $email ;
        parent::__construct( INIT::$DQF_GENERIC_USERNAME, INIT::$DQF_GENERIC_PASSWORD );
    }

    protected function getHeaders( $headers ) {
        return $headers + ['email' => $this->userEmail ] ;
    }

    public function filterHeaders( IBaseStruct $struct ) {
        $headers = $struct->getHeaders() + ['email' => $this->userEmail ] ;
        return $headers ;
    }

}