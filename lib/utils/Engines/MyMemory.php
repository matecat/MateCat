<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/02/15
 * Time: 18.53
 * 
 */

class Engines_MyMemory extends Engines_AbstractEngine implements Engines_EngineInterface {

    protected $_config = array(
            'segment'       => null,
            'translation'   => null,
            'tnote'         => null,
            'source_lang'   => null,
            'target_lang'   => null,
            'email'         => null,
            'prop'          => null,
            'get_mt'        => 1,
            'id_user'       => null,
            'num_result'    => 3,
            'mt_only'       => false,
            'isConcordance' => false,
            'isGlossary'    => false,
    );

    /**
     * @param $engineRecord
     *
     * @throws Exception
     */
    public function __construct( $engineRecord ) {
        parent::__construct( $engineRecord );
        if ( $this->engineRecord->type != "TM" ) {
            throw new Exception( "Engine {$this->engineRecord->id} is not a TMS engine, found {$this->engineRecord->type} -> {$this->engineRecord->class_load}" );
        }
    }

    protected function _decode( $rawValue ){
        if( is_string( $rawValue ) ) {
            $decoded = json_decode( $rawValue, true );
        } else {
            $decoded = $rawValue; // already decoded in case of error
        }
        return new Engines_Results_TMS( $decoded );
    }

    /**
     * @param $_config
     *
     * @return Engines_Results_TMS
     */
    public function get( $_config ) {

        $parameters               = array();
        $parameters[ 'q' ]        = $_config[ 'segment' ];
        $parameters[ 'langpair' ] = $_config[ 'source_lang' ] . "|" . $_config[ 'target_lang' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];
        $parameters[ 'mt' ]       = $_config[ 'get_mt' ];
        $parameters[ 'numres' ]   = $_config[ 'num_result' ];

        ( $_config[ 'isConcordance' ] ? $parameters[ 'conc' ] = 'true' : null );
        ( $_config[ 'mt_only' ] ? $parameters[ 'mtonly' ] = '1' : null );

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = array( $_config[ 'id_user' ] );
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        ( !$_config[ 'isGlossary' ] ? $function = "translate_relative_url" : $function = "gloss_get_relative_url" );

        $this->call( $function, $parameters );

        return $this->result;

    }

    public function set( $_config ) {

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'tnote' ]    = $_config[ 'tnote' ];
        $parameters[ 'langpair' ] = $_config[ 'source_lang' ] . "|" . $_config[ 'target_lang' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];
        $parameters[ 'prop' ]     = $_config[ 'prop' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if( ! is_array( $_config['id_user'] ) ) $_config['id_user'] = array( $_config['id_user'] );
            $parameters['key'] = implode(",", $_config['id_user']);
        }

        ( !$_config['isGlossary']   ? $function = "contribute_relative_url" : $function = "gloss_set_relative_url" );

        $this->call( $function, $parameters );

        if ( $this->result->responseStatus != "200" ) {
            return false;
        }

        return true;

    }

    public function delete( $_config ) {

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'langpair' ] = $_config[ 'source_lang' ] . "|" . $_config[ 'target_lang' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if( ! is_array( $_config['id_user'] ) ) $_config['id_user'] = array( $_config['id_user'] );
            $parameters['key'] = implode(",", $_config['id_user']);
        }

        ( !$_config['isGlossary']   ? $function = "delete_relative_url" : $function = "gloss_delete_relative_url" );

        $this->call( $function, $parameters );

        if ($this->result->responseStatus != "200") {
            return false;
        }
        return true;

    }

    public function update( $_config ) {

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'langpair' ] = $_config[ 'source_lang' ] . "|" . $_config[ 'target_lang' ];
        $parameters[ 'tnote' ]    = $_config[ 'tnote' ];
        $parameters[ 'prop' ]     = $_config[ 'prop' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if( ! is_array( $_config['id_user'] ) ) $_config['id_user'] = array( $_config['id_user'] );
            $parameters['key'] = implode(",", $_config['id_user']);
        }

        $this->call( "gloss_update_relative_url" , $parameters);

        if ($this->result->responseStatus != "200") {
            return false;
        }
        return true;

    }

}