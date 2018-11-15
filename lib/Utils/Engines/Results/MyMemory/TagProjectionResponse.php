<?php

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 17/05/16
 * Time: 11:49
 */
class Engines_Results_MyMemory_TagProjectionResponse extends Engines_Results_AbstractResponse {

    public function __construct( $response ){
        $Filter = \SubFiltering\Filter::getInstance( $this->featureSet );
        $this->responseData    = isset( $response[ 'data' ][ 'translation' ] ) ? $Filter->fromLayer0ToLayer2( $response[ 'data' ][ 'translation' ] ) : '';
    }

}