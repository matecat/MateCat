<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/03/15
 * Time: 19.02
 */

abstract class Engines_Results_AbstractResponse {

    public $responseStatus = "";
    public $responseDetails = "";
    public $responseData = "";

    /**
     * @var \Engines_Results_ErrorMatches
     */
    public $error;

    protected $_rawResponse = "";

    public static function getInstance( $result ){

        $class = get_called_class(); // late static binding, note: php >= 5.3
        $instance = new $class( $result );

        if ( is_array( $result ) and array_key_exists( "error", $result ) ) {
            $instance->error = new Engines_Results_ErrorMatches( $result[ 'error' ] );
        }

        return $instance;

    }

} 