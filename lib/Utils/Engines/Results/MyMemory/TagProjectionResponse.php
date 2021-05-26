<?php

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 17/05/16
 * Time: 11:49
 */

use Matecat\SubFiltering\MateCatFilter;

class Engines_Results_MyMemory_TagProjectionResponse extends Engines_Results_AbstractResponse {

    public function __construct( $response ){
        $Filter = MateCatFilter::getInstance( $this->featureSet, null, null, [] );
        $this->responseData    = isset( $response[ 'data' ][ 'translation' ] ) ? $Filter->fromLayer0ToLayer2( $response[ 'data' ][ 'translation' ] ) : '';
    }

}