<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/03/2017
 * Time: 15:14
 */

namespace Features\Dqf\Service\Struct;


use Features\Dqf\Utils\Functions;

class MasterFileRequestStruct extends BaseRequestStruct {

    // public $projectId ;

    public $sessionId ;
    public $apiKey ;
    public $projectKey;

    public $name ;
    public $numberOfSegments ;
    public $clientId ;

    protected $_unscopedClientId ;

    public function getHeaders() {
        return $this->toArray(['sessionId', 'apiKey', 'projectKey']);
    }

    public function __set( $name, $value ) {
        if ( $name == '_unscopedClientId' ) {
            $this->_unscopedClientId = $value ;
            $this->clientId = Functions::scopeId( $value );
        }

        if ( $name == 'clientId' ) {
            $this->_unscopedClientId = Functions::descope( $value ) ;
            $this->clientId = $value ;
        }
    }

}