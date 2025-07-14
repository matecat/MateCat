<?php


/**
 * @property string                       responseDetails
 * @property string                       responseData
 * @property string                       responseStatus
 * @property mixed                        translatedText
 * @property Engines_Results_ErrorMatches error
 */
class Engines_Results_MMT_ExceptionError extends Engines_Results_AbstractResponse {

    public function __construct( $result ) {

        $this->error = new Engines_Results_ErrorMatches( [
                'message' => $this->responseDetails,
                'code'    => $result[ 'responseStatus' ]
        ] );

    }

}