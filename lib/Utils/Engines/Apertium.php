<?php

/**
 * Created by PhpStorm.
 * @property string client_secret
 * @author egomez-prompsit egomez@prompsit.com
 * Date: 29/07/15
 * Time: 12.17
 *
 */

class Engines_Apertium extends Engines_AbstractEngine {

    protected $_config = array(
            'segment'     => null,
            'source'      => null,
            'target'      => null,
            'key'     => null,
    );

    public function __construct($engineRecord) {
        parent::__construct($engineRecord);
        if ( $this->engineRecord->type != "MT" ) {
            throw new Exception( "Engine {$this->engineRecord->id} is not a MT engine, found {$this->engineRecord->type} -> {$this->engineRecord->class_load}" );
        }
    }

    /**
     * @param $lang
     *
     * @return mixed
     * @throws Exception
     */
    protected function _fixLangCode( $lang ) {
        return $lang;
    }

    /**
     * @param $rawValue
     * @param array $parameters
     * @param null $function
     * @return array|Engines_Results_MT
     * @throws Exception
     */
    protected function _decode( $rawValue, array $parameters = [], $function = null ){
        $all_args =  func_get_args();

        if( is_string( $rawValue ) ) {
            $original = json_decode( $all_args[1]["data"] , true );
            $decoded = json_decode( $rawValue, true );
            $decoded = array(
                    'data' => array(
                            "translations" => array(
                                    array( 'translatedText' =>  $this->_resetSpecialStrings( $decoded[ "text" ] ) )
                            )
                    )
            );
        } else {
            $decoded = $rawValue; // already decoded in case of error
        }

        $mt_result = new Engines_Results_MT( $decoded );

        if ( $mt_result->error->code < 0 ) {
            $mt_result = $mt_result->get_as_array();
            $mt_result['error'] = (array)$mt_result['error'];
            return $mt_result;
        }

        $mt_match_res = new Engines_Results_MyMemory_Matches(
                $this->_preserveSpecialStrings( $original["text"]),
                $mt_result->translatedText,
                100 - $this->getPenalty() . "%",
                "MT-" . $this->getName(),
                date( "Y-m-d" )
        );

        $mt_res = $mt_match_res->getMatches();

        return $mt_res;

    }

    public function get( $_config ) {
        $_config[ 'segment' ] = $this->_preserveSpecialStrings( $_config[ 'segment' ] );

        $param_data = json_encode(array(
                "mtsystem" => "apertium",
                "src" => $_config[ 'source' ],
                "trg" => $_config[ 'target' ],
                "text" => $_config[ 'segment' ]
        ));

        $parameters = array();
        if (  $this->client_secret != '' && $this->client_secret != null ) {
            $parameters[ 'key' ] = $this->client_secret;
        }
        $parameters['func'] = "translate";
        $parameters['data'] = $param_data;

        $this->_setAdditionalCurlParams( array(
                        CURLOPT_POST       => true,
                        CURLOPT_RETURNTRANSFER => true
                )
        );
        $this->call( "translate_relative_url", $parameters, false);

        return $this->result;

    }

    public function set( $_config ) {

        //if engine does not implement SET method, exit
        return true;
    }

    public function update( $config ) {

        //if engine does not implement UPDATE method, exit
        return true;
    }

    public function delete( $_config ) {

        //if engine does not implement DELETE method, exit
        return true;

    }

}