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
class Engines_LetsMT extends Engines_AbstractEngine implements Engines_EngineInterface {

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

                if ( $this->use_qe && floatval( $parsed[ 'qualityEstimate' ] ) < $this->minimum_qe ) {
                    $mt_result = array(
                            'error' => array(
                                    'code'    => -3001,
                                    'message' => 'Translation QE score below treshold'
                            )
                    );

                    return $mt_result;
                }

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

                $mt_res                          = $mt_match_res->get_as_array();
                $mt_res[ 'sentence_confidence' ] = $mt_result->sentence_confidence; //can be null

                return $mt_res;
            }
            elseif ( !empty( $parsed[ 'System' ] ) ) {
                // this is a response from a getSystemList request

                $decoded = array();
                foreach ( $parsed[ 'System' ] as $systemData ) {
                    $statusName = "";
                    $status     = "";
                    foreach ( $systemData[ 'Metadata' ] as $value ) {
                        if ( $value[ 'Key' ] === 'status' ) {
                            $status = $value[ 'Value' ];
                            switch ( $status ) {
                                case "running":
                                    $statusName = "Running";
                                    break;
                                case "queuingtransl":
                                    $statusName = "Queuing";
                                    break;
                                case "notstarted":
                                    $statusName = "Not Started";
                                    break;
                                case "nottrained":
                                    $statusName = "Not Trained";
                                    break;
                                case "error":
                                    $statusName = "Not Trained";
                                    break;
                                case "training":
                                    $statusName = "Training";
                                    break;
                                case "standby":
                                    $statusName = "Standby";
                                    break;
                                default:
                                    $statusName = $value[ 'Value' ];
                                    break;
                            }
                            break;
                        }
                    }
                    $systemName                     = sprintf( '%s (%s)',
                            $systemData[ 'Title' ][ 'Text' ],
                            $statusName );
                    $systemMetadata                 = array(
                            'source-language-code' => $systemData[ 'SourceLanguage' ][ 'Code' ],
                            'target-language-code' => $systemData[ 'TargetLanguage' ][ 'Code' ],
                            'source-language-name' => $systemData[ 'SourceLanguage' ][ 'Name' ][ 'Text' ],
                            'target-language-name' => $systemData[ 'TargetLanguage' ][ 'Name' ][ 'Text' ],
                            'status'               => $status
                    );
                    $decoded[ $systemData[ 'ID' ] ] = array(
                            'name'     => $systemName,
                            'metadata' => $systemMetadata
                    );
                }
                asort( $decoded );

                return $decoded;
            }
            elseif ( !empty( $parsed[ 0 ] ) && !empty( $parsed[ 0 ][ 'CorpusId' ] ) ) {
                // this is a response from getSystemTermCorpora request

                $decoded = array();
                foreach ( $parsed as $termData ) {
                    if ( $termData[ 'Status' ] == 'Ready' ) {
                        $decoded[ $termData[ 'CorpusId' ] ] = $termData[ 'Title' ];
                    }
                }
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

        return $decoded;

    }

    public function get( $_config ) {

        $_config[ 'segment' ] = $this->_preserveSpecialStrings( $_config[ 'segment' ] );
        $_config[ 'source' ]  = $this->_fixLangCode( $_config[ 'source' ] );
        $_config[ 'target' ]  = $this->_fixLangCode( $_config[ 'target' ] );

        // if any of the engine languages is not set, continue, else check if engine and document languages match
        if ( $this->source_lang && $_config[ 'source' ] && $this->target_lang && $_config[ 'target' ] &&
                ( $this->source_lang !== $_config[ 'source' ] || $this->target_lang !== $_config[ 'target' ] )
        ) {
            return array(
                    'error' => array(
                            'code' => -3002, 'message' => 'Engine and document languages do not match'
                    )
            );
        }

        $parameters               = array();
        $parameters[ 'text' ]     = $_config[ 'segment' ];
        $parameters[ 'appID' ]    = $this->app_id;
        $parameters[ 'systemID' ] = $this->system_id;
        $parameters[ 'clientID' ] = $this->client_id;
        $qeParam                  = $this->use_qe ? ",qe" : "";
        $parameters[ 'options' ]  = "termCorpusId=" . $this->terms_id . $qeParam;

        $this->call( "translate_relative_url", $parameters );

        return $this->result;

    }

    public function set( $_config ) {

        //if engine does not implement SET method, exit
        if ( null == $this->contribute_relative_url ) {
            return true;
        }


        $_config[ 'source' ] = $this->_fixLangCode( $_config[ 'source' ] );
        $_config[ 'target' ] = $this->_fixLangCode( $_config[ 'target' ] );

        // if any of the engine languages is not set, continue, else check if engine and document languages match
        if ( $this->source_lang && $_config[ 'source' ] && $this->target_lang && $_config[ 'target' ] &&
                ( $this->source_lang !== $_config[ 'source' ] || $this->target_lang !== $_config[ 'target' ] )
        ) {
            return array(
                    'error' => array(
                            'code' => -3002, 'message' => 'Engine and document languages do not match'
                    )
            );
        }

        $parameters                  = array();
        $parameters[ 'text' ]        = $_config[ 'segment' ];
        $parameters[ 'appID' ]       = $this->app_id;
        $parameters[ 'systemID' ]    = $this->system_id;
        $parameters[ 'clientID' ]    = $this->client_id;
        $parameters[ 'options' ]     = "termCorpusId=" . $this->terms_id;
        $parameters[ 'translation' ] = $_config[ 'translation' ];

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

    public function getSystemList( $_config ) {

        $parameters               = array();
        $parameters[ 'appID' ]    = $this->app_id;
        $parameters[ 'clientID' ] = $this->client_id;

        $this->call( 'system_list_relative_url', $parameters );

        if ( isset( $this->result[ 'error' ][ 'code' ] ) ) {
            return $this->result;
        }
        $systemList = $this->result;

        return $systemList;

    }

    public function getTermList() {

        $parameters               = array();
        $parameters[ 'appID' ]    = $this->app_id;
        $parameters[ 'clientID' ] = $this->client_id;
        $parameters[ 'systemID' ] = $this->system_id;

        $this->call( 'term_list_relative_url', $parameters );

        $termList = $this->result;
        if ( isset( $termList[ 'error' ][ 'code' ] ) ) {
            return array( 'error' => $termList[ 'error' ] );
        }

        return array( 'terms' => $termList );
    }

    public function wakeUp() {
        $_config              = $this->getConfigStruct();
        $_config[ 'segment' ] = 'wakeup';

        $this->_setAdditionalCurlParams( array(
                CURLOPT_TIMEOUT => 1
        ) );

        $this->get( $_config );
    }
}