<?php

/**
 * Created by PhpStorm.
 * @property string client_secret
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/03/15
 * Time: 12.10
 * 
 */

class Engines_Moses extends Engines_AbstractEngine {

    protected $_config = array(
            'segment'     => null,
            'translation' => null,
            'source'      => null,
            'target'      => null,
            'id_user'     => null,
            'segid'       => null,
    );

    public function __construct($engineRecord) {
        parent::__construct($engineRecord);
        if ( $this->engineRecord->type != "MT" ) {
            throw new Exception( "Engine {$this->engineRecord->id} is not a MT engine, found {$this->engineRecord->type} -> {$this->engineRecord->class_load}" );
        }
    }

    /**
     * @param $rawValue
     *
     * @return array
     */
    protected function _decode( $rawValue ){

        $all_args =  func_get_args();

        if( is_string( $rawValue ) ) {
            $decoded = json_decode( $rawValue, true );
            $decoded['data']['translations'][0]['translatedText'] =  $this->_resetSpecialStrings( $decoded['data']['translations'][0]['translatedText'] );
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
                $this->_resetSpecialStrings( $all_args[ 1 ][ 'q' ] ),
                $mt_result->translatedText,
                100 - $this->getPenalty() . "%",
                "MT-" . $this->getName(),
                date( "Y-m-d" )
        );

        $mt_res                          = $mt_match_res->getMatches();
        $mt_res[ 'sentence_confidence' ] = $mt_result->sentence_confidence; //can be null

        return $mt_res;

    }

    public function get( $_config ) {

        $_config[ 'segment' ] = $this->_preserveSpecialStrings( $_config[ 'segment' ] );
        $_config[ 'source' ]  = $this->_fixLangCode( $_config[ 'source' ] );
        $_config[ 'target' ]  = $this->_fixLangCode( $_config[ 'target' ] );

        $parameters = array();
		$parameters['q'] = $_config[ 'segment' ];
		$parameters['source'] = $_config[ 'source' ];
		$parameters['target'] = $_config[ 'target' ];

        if (  $this->client_secret != '' && $this->client_secret != null ) {
            $parameters[ 'key' ] = $this->client_secret;
        }

        if ( isset( $_config[ 'segid' ] ) && is_numeric( $_config[ 'segid' ] ) ) {
            $parameters[ 'segid' ] = $_config[ 'segid' ];
        }

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

        $parameters                  = array();
        $parameters[ 'segment' ]     = $_config[ 'segment' ];
        $parameters[ 'translation' ] = $_config[ 'translation' ];
        $parameters[ 'source' ]      = $_config[ 'source' ];
        $parameters[ 'target' ]      = $_config[ 'target' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = array( $_config[ 'id_user' ] );
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        $parameters[ 'de' ]          = $_config[ 'email' ];
        $parameters[ 'extra' ]       = $_config[ 'extra' ];

        if ( isset( $_config[ 'segid' ] ) && is_numeric( $_config[ 'segid' ] ) ) {
            $parameters[ 'segid' ] = $_config[ 'segid' ];
        }

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

        //if engine does not implement SET method, exit
        if ( null == $this->delete_relative_url ) {
            return true;
        }

        $_config[ 'source' ] = $this->_fixLangCode( $_config[ 'source' ] );
        $_config[ 'target' ] = $this->_fixLangCode( $_config[ 'target' ] );

        $parameters                  = array();
        $parameters[ 'segment' ]     = $_config[ 'segment' ];
        $parameters[ 'translation' ] = $_config[ 'translation' ];
        $parameters[ 'source' ]      = $_config[ 'source' ];
        $parameters[ 'target' ]      = $_config[ 'target' ];
        $parameters[ 'de' ]          = $_config[ 'email' ];
        $parameters[ 'extra' ]       = $_config[ 'extra' ];

        if ( isset( $_config[ 'segid' ] ) && is_numeric( $_config[ 'segid' ] ) ) {
            $parameters[ 'segid' ] = $_config[ 'segid' ];
        }

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = array( $_config[ 'id_user' ] );
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        $this->call( "delete_relative_url", $parameters );

        if ( $this->result[ 'error' ][ 'code' ] < 0 ) {
            return $this->result;
        }

        return true;

    }


}