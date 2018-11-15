<?php

/**
 * @author Rihards Krislauks rihards.krislauks@tilde.lv / rihards.krislauks@gmail.com
 */

/**
 * Class Engines_LetsMT
 * @property string oauth_url
 * @property int    token_endlife
 * @property string token
 * @property string client_id
 * @property string client_secret
 */
class Engines_LetsMT extends Engines_AbstractEngine {

    protected $_config = array(
            'segment'     => null,
            'translation' => null,
            'source'      => null,
            'target'      => null
    );

    public function __construct( $engineRecord ) {
        parent::__construct( $engineRecord );
        if ( $this->engineRecord->type != "MT" ) {
            throw new Exception( "Engine {$this->engineRecord->id} is not a MT engine, found {$this->engineRecord->type} -> {$this->engineRecord->class_load}" );
        }
    }

    /**
     * @param $rawValue
     *
     * @return array
     */
    protected function _decode( $rawValue ) {

        $all_args = func_get_args();

        if ( is_string( $rawValue ) ) {
            $parsed = json_decode( $rawValue, true );
            if ( !empty( $parsed[ 'translation' ] ) ) {
                // this is a response from a translate request

                $qe_score = floatval( $parsed[ 'qualityEstimate' ] );

                $decoded = array(
                        'data' => array(
                                "translations" => array(
                                        array( 'translatedText' => $this->_resetSpecialStrings( $parsed[ 'translation' ] ) )
                                )
                        )
                );

                $mt_result = new Engines_Results_MT( $decoded );

                if ( $mt_result->error->code < 0 ) {
                    $mt_result            = $mt_result->get_as_array();
                    $mt_result[ 'error' ] = (array)$mt_result[ 'error' ];

                    return $mt_result;
                }

                $mt_match_res = new Engines_Results_MyMemory_Matches(
                        $this->_resetSpecialStrings( $all_args[ 1 ][ 'text' ] ),
                        $mt_result->translatedText,
                        100 - $this->getPenalty() . "%",
                        "MT-" . $this->getName(),
                        date( "Y-m-d" )
                );

                $mt_res                          = $mt_match_res->getMatches();
                $mt_res[ 'sentence_confidence' ] = $mt_result->sentence_confidence; //can be null

                $retObj = new stdClass();
                $retObj->mtResult = $mt_res;
                $retObj->qeScore = $qe_score;
                return $retObj;
            }
        }
        else {

            // get the response body for the error message
            $parsed = json_decode( $rawValue[ 'error' ][ 'response' ], true );

            if ( !empty( $parsed[ 'ErrorMessage' ] ) ) {
                $mt_code = intval( $parsed[ 'ErrorCode' ] );
                $code    = $mt_code == 21 ? '-2002' : '-2001'; // if engine is waking up render message as a warning (code -2002) else as error (code -2001).
                $message = sprintf( "(%s) %s", $parsed[ 'ErrorCode' ], $parsed[ 'ErrorMessage' ] );
                $decoded = array(
                        'error' => array(
                                'code'       => $code,
                                'message'    => $message,
                                'created_by' => "MT-" . $this->getName()
                        )
                );
            } // no response body for the error message
            elseif( is_array( $rawValue['error'] ) ){

                //Curl Error also ( Timeout/DNS/Socket )
                $decoded = new Engines_Results_MT( $rawValue );
                if ( $decoded->error->code <= 0 ) {
                    $decoded            = $decoded->get_as_array();
                    $decoded[ 'error' ] = (array)$decoded[ 'error' ];
                }

            }
            else {

                $decoded = array( 'error' => $rawValue[ 'error' ] );
                if ( strpos( $decoded[ 'error' ], 'Server Not Available (http status 401)' ) !== false ) {
                    $decoded[ 'error' ][ 'message' ] = 'Invalid Client ID.';

                }

            }

        }

        
        $retObj = new stdClass();
        $retObj->errorResult = $decoded;
        return $retObj;
    }

    public function get( $_config ) {

        $_config[ 'segment' ] = $this->_preserveSpecialStrings( $_config[ 'segment' ] );
        $_config[ 'source' ]  = $this->_fixLangCode( $_config[ 'source' ] );
        $_config[ 'target' ]  = $this->_fixLangCode( $_config[ 'target' ] );
        $src = $_config [ 'source' ];
        $trg = $_config [ 'target' ];
        
        $plugin_config = json_decode($this->config_json, TRUE);
        $lang_key = $src . '-' . $trg;
        if (!isset($plugin_config[ 'systems' ][ $lang_key ])){
            return array(
                'error' => array(
                    'code' => -3002, 'message' => "The MT engine doesn't support the document's language"
                )
            );
        }
        $lang_config = $plugin_config[ 'systems' ][ $lang_key ];
        $parameters = $lang_config[ 'params' ];
        
        $parameters[ 'text' ]     = $_config[ 'segment' ];
        $parameters[ 'clientID' ] = $plugin_config[ 'client-id' ];

        $this->call( "translate_relative_url", $parameters );

        if (isset($this->result->errorResult)){
            return $this->result->errorResult;
        }

        $qe_threshold = $lang_config[ 'qeThreshold' ];
        if (isset($qe_threshold)
                && $this->result->qeScore != null
                && floatval( $qe_threshold ) > $this->result->qeScore){
            $mt_result = null;
        } else {
            $mt_result = $this->result->mtResult;
        }
        return $mt_result;

    }

    public function set( $_config ) {

        //if engine does not implement SET method, exit
        if ( null == $this->contribute_relative_url ) {
            return true;
        }


        $_config[ 'source' ] = $this->_fixLangCode( $_config[ 'source' ] );
        $_config[ 'target' ] = $this->_fixLangCode( $_config[ 'target' ] );
        $src = $_config [ 'source' ];
        $trg = $_config [ 'target' ];
        
        $plugin_config = json_decode($this->config_json, TRUE);
        $lang_key = $src . '-' . $trg;
        if (!isset($plugin_config[ 'systems' ][ $lang_key ])){
            return array(
                'error' => array(
                    'code' => -3002, 'message' => "The MT engine doesn't support the document's language"
                )
            );
        }
        $lang_config = $plugin_config[ 'systems' ][ $lang_key ];
        $parameters = $lang_config[ 'params' ];
        
        $parameters[ 'text' ]     = $_config[ 'segment' ];
        $parameters[ 'translation' ] = $_config[ 'translation' ];
        $parameters[ 'clientID' ] = $plugin_config[ 'client-id' ];

        $this->call( "contribute_relative_url", $parameters );

        return $this->result;

    }

    public function update( $config ) {
        // TODO: Implement update() method.
        if ( null == $this->update_relative_url ) {
            return true;
        }
    }

    public function delete( $_config ) {
        // TODO: implement delete() method
        //if engine does not implement SET method, exit
        if ( null == $this->delete_relative_url ) {
            return true;
        }
    }

    // TODO: implement something similar
//    public function wakeUp() {
//        $_config              = $this->getConfigStruct();
//        $_config[ 'segment' ] = 'wakeup';
//
//        $this->_setAdditionalCurlParams( array(
//                CURLOPT_TIMEOUT => 1
//        ) );
//
//        $this->get( $_config );
//    }
}