<?php


/**
 * @property string responseDetails
 * @property string responseData
 * @property string responseStatus
 * @property mixed  translatedText
 * @property Engines_Results_ErrorMatches error
 */
class Engines_Results_MMT_MT extends Engines_Results_AbstractResponse {

    public function __construct( $result ) {

        if( $result[ 'responseStatus' ] == 200 ){
            if ( is_array( $result ) and @array_key_exists( "translatedText", $result[ 'responseData' ] ) ) {
                $this->translatedText = $result[ 'responseData' ][ 'translatedText' ];
            }

            $this->responseDetails = 'OK';
            $this->responseData = isset( $result[ 'responseData' ] ) ? $result[ 'responseData' ] : '';

        } else{

            $this->responseDetails = isset( $result[ 'responseDetails' ] ) ? $result[ 'responseDetails' ] : '';
            $this->error = new Engines_Results_ErrorMatches( [ 'message' =>  $result[ 'responseDetails' ], 'code' => $result[ 'responseStatus' ] ] );

        }

        $this->responseStatus  = isset( $result[ 'responseStatus' ] ) ? $result[ 'responseStatus' ] : '';

    }

    public function get_as_array() {
        if( $this->error != "" ) $this->error = $this->error->get_as_array();
        return (array)$this;
    }

}