<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/03/2017
 * Time: 13:02
 */

namespace Features\Dqf\Service\Struct;


use INIT;

abstract class BaseRequestStruct extends BaseStruct implements IBaseStruct {

    abstract function getHeaders() ;

    /**
     * Returns all the params that are not pathParams or headers.
     * This method shold be ok to return params for `formData` or json for PUT requests.
     *
     * @return array
     */
    public function getParams() {
        $params = array_diff_key( $this->toArray(), $this->getHeaders() );
        $params = array_diff_key( $params, $this->getPathParams() );
        return $params ;
    }

    public function getPathParams() {
        return array();
    }

    public function __construct( array $array_params = array() ) {
        if ( !isset( $array_params['apiKey'] ) ) {
            $array_params['apiKey'] = INIT::$DQF_API_KEY ;
        }

        parent::__construct( $array_params );
    }


}