<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/09/14
 * Time: 15.01
 */
include_once INIT::$MODEL_ROOT . "/queries.php";

class TmKeyManagement_TmKeyManagement {

    /**
     * Returns a TmKeyManagement_TmKeyStruct object. <br/>
     * If a proper associative array is passed, it fills the fields
     * with the array values.
     * @param array|null $tmKey_arr An associative array having
     *                              the same keys of a
     *                              TmKeyManagement_TmKeyStruct object
     * @return TmKeyManagement_TmKeyStruct The converted object
     */
    public static function getTmKeyStructure( $tmKey_arr = null ) {
        return new TmKeyManagement_TmKeyStruct( $tmKey_arr );
    }

    /**
     * Converts a string representing a json_encoded array of TmKeyManagement_TmKeyStruct into an array
     * and filters the elements according to the grants passed.
     *
     * @param   $jsonTmKeys  string  A json string representing an array of TmKeyStruct Objects
     * @param   $grant_level string  One of the following strings : "r", "w", "rw"
     * @param   $type string string  One of the following strings : "tm", "glossary", "tm,glossary"
     * @return  array|mixed  An array of TmKeyManagement_TmKeyStruct objects
     * @throws  Exception    Throws Exception if grant_level string is wrong or if type string is wrong
     *
     * @see TmKeyManagement_TmKeyStruct
     */
    public static function getJobTmKeys( $jsonTmKeys, $grant_level = 'rw', $type = "tm") {
        $accepted_grants = array( "r", "w", "rw" );
        $accepted_types = array( "tm", "glossary", "tm,glossary");

        if ( !in_array( $grant_level, $accepted_grants ) ) {
            throw new Exception ( __METHOD__ . " -> Invalid grant string." );
        }

        if ( !in_array($type, $accepted_types) ) {
            throw new Exception ( __METHOD__ . " -> Invalid type string." );
        }

        $tmKeys = json_decode( $jsonTmKeys, true );

        if ( is_null( $tmKeys ) ) {
            throw new Exception ( __METHOD__ . " -> Invalid JSON " );
        }

        //filter results by grants
        switch ( $grant_level ) {
            case 'r' :
                $tmKeys = array_filter(
                    $tmKeys,
                    array( "TmKeyManagement_TmKeyManagement", 'filterTmKeysByReadGrant' )
                );
                break;
            case 'w' :
                $tmKeys = array_filter(
                    $tmKeys,
                    array( "TmKeyManagement_TmKeyManagement", 'filterTmKeysByWriteGrant' )
                );
                break;
            default  :
                break;
        }

        switch( $type ){
            case 'tm' :
                $tmKeys = array_filter(
                        $tmKeys,
                        array( "TmKeyManagement_TmKeyManagement", 'filterTmKeysByTmType' )
                );
                break;
            case 'glossary':
                $tmKeys = array_filter(
                        $tmKeys,
                        array( "TmKeyManagement_TmKeyManagement", 'filterTmKeysByGlossaryType' )
                );
                break;
            default:
                break;
        }

        $tmKeys = array_values( $tmKeys );
        $tmKeys = array_map( array( 'self', 'getTmKeyStructure' ), $tmKeys );

        return $tmKeys;
    }

    /**
     * @param $id_job int
     * @param $job_pass string
     * @param $tm_keys array
     * @return int|null Returns null if all is ok, otherwise it returns the error code of the mysql Query
     */
    public static function setJobTmKeys( $id_job, $job_pass, $tm_keys ) {
        return setJobTmKeys( $id_job, $job_pass, json_encode( $tm_keys ) );
    }

    /**
     * Converts an array of strings representing a json_encoded array
     * of TmKeyManagement_TmKeyStruct objects into the corresponding array.
     *
     * @param $jsonTmKeys_array array An array of strings representing a json_encoded array of TmKeyManagement_TmKeyStruct objects
     * @return array                  An array of TmKeyManagement_TmKeyStruct objects
     * @throws Exception              Throws Exception if the input is not an array or if a string is not a valid json
     * @see TmKeyManagement_TmKeyStruct
     */
    public static function getOwnerKeys( $jsonTmKeys_array ) {

        if ( !is_array( $jsonTmKeys_array ) || is_null( $jsonTmKeys_array ) ) {
            Log::doLog( __METHOD__ . " -> Invalid Array." );
            Log::doLog( var_export( $jsonTmKeys_array, true ) );

            throw new Exception( "Invalid array", -1 );
        }

        $result_arr = array();

        foreach( $jsonTmKeys_array as $pos => $tmKey ){

            $tmKey = json_decode($tmKey, true);

            if ( is_null( $tmKey ) ) {
                Log::doLog( __METHOD__ . " -> Invalid JSON." );
                Log::doLog( var_export( $tmKey, true ) );
                throw new Exception ( "Invalid JSON", -2 );
            }

            $tmKey = array_filter( $tmKey, array( 'self', 'filterTmKeysByOwnerTrue' ) );

            $result_arr[] = $tmKey;

        }

        /**
         *
         * Note: Take the shortest array of keys, it's like an intersection between owner keys
         */
        asort( $result_arr );

        //take only the first Job entries
        $result_arr = array_shift( $result_arr );

        //convert tm keys into TmKeyManagement_TmKeyStruct objects
        $result_arr = array_map( array( 'self', 'getTmKeyStructure' ), $result_arr );

        return $result_arr;
    }

    /**
     * Converts an array of strings representing a json_encoded array
     * of TmKeyManagement_TmKeyStruct objects into an array of TmKeyManagement_TmKeyStruct objects
     * @param $jsonTmKeys_array
     * @return mixed
     * @throws Exception
     */
    public static function array2TmKeyStructs( $jsonTmKeys_array ) {

        $tmKeys_array = array_map( 'json_decode', $jsonTmKeys_array,
            //this workaround passes to array_map an array of true parameters
            //for each string that will be json_decoded
            array_fill(
                0,
                count( $jsonTmKeys_array ),
                true )
        );

        if ( is_null( $tmKeys_array ) || empty( $tmKeys_array ) ) {
            Log::doLog( __METHOD__ . " -> Invalid JSON." );
            Log::doLog( var_export( $jsonTmKeys_array, true ) );
            throw new Exception( "Invalid JSON" );
        }

        $result_arr = array();
        foreach ( $tmKeys_array as $tmKey_arr ) {
            $result_arr = array_merge( $result_arr, $tmKey_arr );
        }

        //eliminate duplicates
        $result_arr = array_unique( $result_arr );

        $result_arr = array_map( array( 'self', 'getTmKeyStructure' ), $result_arr );

        return $result_arr;
    }

    /**
     * Filters the elements of an array checking if read grant is true
     *
     * @param $tm_key array An associative array with the following keys:<br/>
     * <pre>
     *          tm      : int - 1 if it's a tm key
     *          glos    : int - 1 if it's a glossary key
     *          owner   : boolean
     *          key     : string
     *          r       : int     - 0 or 1. Read privilege
     *          w       : int     - 0 or 1. Write privilege
     * </pre>
     *
     * @return bool This function returns whether the element is filtered or not
     */
    private static function filterTmKeysByReadGrant( $tm_key ) {
        return $tm_key[ 'r' ] == true;
    }

    /**
     * Filters the elements of an array checking if write grant is true
     *
     * @param $tm_key array An associative array with the following keys:<br/>
     * <pre>
     *          tm      : int - 1 if it's a tm key
     *          glos    : int - 1 if it's a glossary key
     *          owner   : boolean
     *          key     : string
     *          r       : int     - 0 or 1. Read privilege
     *          w       : int     - 0 or 1. Write privilege
     * </pre>
     *
     * @return bool This function returns whether the element is filtered or not
     */
    private static function filterTmKeysByWriteGrant( $tm_key ) {
        return $tm_key[ 'w' ] == true;
    }

    /**
     * Filters the elements of an array checking if tm field is true
     *
     * @param $tm_key array An associative array with the following keys:<br/>
     * <pre>
     *          tm      : int - 1 if it's a tm key
     *          glos    : int - 1 if it's a glossary key
     *          owner   : boolean
     *          key     : string
     *          r       : int     - 0 or 1. Read privilege
     *          w       : int     - 0 or 1. Write privilege
     * </pre>
     *
     * @return bool This function returns whether the element is filtered or not
     */
    private static function filterTmKeysByTmType( $tm_key ){
        return $tm_key[ 'tm' ] == true;
    }

    /**
     * Filters the elements of an array checking if glos field is true
     *
     * @param $tm_key array An associative array with the following keys:<br/>
     * <pre>
     *          tm      : int - 1 if it's a tm key
     *          glos    : int - 1 if it's a glossary key
     *          owner   : boolean
     *          key     : string
     *          r       : int     - 0 or 1. Read privilege
     *          w       : int     - 0 or 1. Write privilege
     * </pre>
     *
     * @return bool This function returns whether the element is filtered or not
     */
    private static function filterTmKeysByGlossaryType( $tm_key ){
        return $tm_key[ 'glos' ] == true;
    }

    /**
     * Filters the elements of an array checking if owner flag is true
     * @param $tm_key TmKeyManagement_TmKeyStruct
     * @return bool This function returns whether the elements is filtered or not
     */
    private static function filterTmKeysByOwnerTrue(  $tm_key ) {
        return $tm_key['owner'] == true;
    }
}