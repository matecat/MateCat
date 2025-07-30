<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 09/10/14
 * Time: 12.43
 *
 */

/**
 * Class TmKeyManagement_Filter
 *
 * Filters the elements of an array representation of TmKeyManagement_TmKeyStruct by required values
 *
 * @see TmKeyManagement_TmKeyManagement
 * @see TmKeyManagement_TmKeyStruct
 *
 */
class TmKeyManagement_Filter {

    /**
     * Type translator
     */
    const ROLE_TRANSLATOR = 'translator';

    /**
     * Type Revisor
     */
    const ROLE_REVISOR    = 'revisor';

    /**
     * A generic Owner Type
     */
    const OWNER           = 'owner';

    /**
     * @var int the user id required for filtering
     */
    protected $__uid;

    /**
     * @see TmKeyManagement_Filter::setTmType
     * @var array Filter types
     */
    protected $_type = array();

    /**
     * @see TmKeyManagement_Filter::setGrants
     * @var string Required grants
     */
    protected $_required_grant;

    /**
     * This variable holds the map to look for the right key in the JSON structure
     * Filtering can be by Translator or By Revisor
     *
     * @var array
     */
    public static $GRANTS_MAP = array(
        self::ROLE_TRANSLATOR => array( "r" => 'r_transl', "w" => 'w_transl' ),
        self::ROLE_REVISOR    => array( "r" => 'r_rev', "w" => 'w_rev' ),
        self::OWNER           => array( 'r' => 'r', 'w' => 'w' )
    );

    /**
     * White list of the accepted types constants
     * @var array
     */
    protected $_accepted_types  = array( "tm", "glos", "tm,glos" );

    /**
     * @param int|null $uid
     */
    public function __construct( $uid = null ) {
        $this->__uid = (int)$uid;

    }

    /**
     * I have to get all owner keys that match required filters and all translator keys that match these filters too
     * @param $tm_key
     *
     * @return bool
     */
    public function byTranslator( $tm_key ) {

        if ( $tm_key[ 'owner' ] == true ) {
            $is_an_owner_key = true;
            $role            = self::OWNER;
        } else {
            $is_an_owner_key = false;
            $role            = self::ROLE_TRANSLATOR;
        }

        /**
         * For Old key struct compatibility, avoid
         *      WARNING: Undefined index: uid_transl
         */
        $i_can_see_the_key = false;

        //it's mine???
        // - if you're not anonymous
        // - o se la chiave è owner E SEI l'owner
        // - oppure se la chiave appartiene all'utente
        //
        if( !empty( $this->__uid ) && ( !empty($tm_key[ 'uid_transl' ]) ) ){
            $i_can_see_the_key = $this->__uid == $tm_key[ 'uid_transl' ];
        }

        return ( $is_an_owner_key || $i_can_see_the_key )
            && $this->_hasRightGrants( $tm_key, $role )
            && $this->_isTheRightType( $tm_key );
    }

    /**
     * @param $tm_key
     *
     * @return bool
     */
    public function byRevisor( $tm_key ) {

        if ( $tm_key[ 'owner' ] == true ) {
            $is_an_owner_key = true;
            $role            = self::OWNER;
        } else {
            $is_an_owner_key = false;
            $role            = self::ROLE_REVISOR;
        }

        /**
         * For Old key struct compatibility, avoid
         *      WARNING: Undefined index: uid_transl
         */
        $i_can_see_the_key = false;
        if( array_key_exists( 'uid_transl', $tm_key ) ){ // this is a new key type

            if( is_null( $tm_key[ 'uid_transl' ] ) && is_null( $tm_key[ 'uid_rev' ] ) ){
                //this is an owner key or anonymous one, so i can use it
                $i_can_see_the_key = true;
            } else {
                //it's mine???
                $i_can_see_the_key = $this->__uid == $tm_key[ 'uid_rev' ];
            }

        }

        return ( $is_an_owner_key || $i_can_see_the_key )
            && $this->_hasRightGrants( $tm_key, $role )
            && $this->_isTheRightType( $tm_key );
    }

    /**
     *
     *
     * @param $tm_key
     *
     * @return bool
     */
    public function byOwner( $tm_key ){
        return ( $tm_key[ self::OWNER ] == true )
            && $this->_hasRightGrants( $tm_key, self::OWNER )
            && $this->_isTheRightType( $tm_key );
    }

    /**
     * Set required grants<br />
     * If no grants filter is set, the filtering will be skipped.<br /><br />
     * Grants can be "r", "w", "rw"<br />
     * ---> "rw" means that either "r" and "w" must be true

     *
     * @param string $grant_level
     *
     * @return $this
     * @throws Exception
     */
    public function setGrants( $grant_level = 'rw' ){

        if ( !in_array( $grant_level, Constants_TmKeyPermissions::$_accepted_grants ) ) {
            throw new Exception ( __METHOD__ . " -> Invalid grant string." );
        }

        $this->_required_grant = $grant_level;

        return $this;

    }

    /**
     * Set the filter types<br />
     * If no type filter is set, the filtering will be skipped.<br /><br />
     * Filters can be "tm", "gloss", "tm,gloss"<br />
     * ---> "tm,gloss" means that either "tm" and "gloss" must be true
     *
     * @param $type
     *
     * @return $this
     * @throws Exception
     */
    public function setTmType( $type ){

        if ( !in_array( $type, $this->_accepted_types ) ) {
            throw new Exception ( __METHOD__ . " -> Invalid type string." );
        }

        $this->_type = explode( ',', $type );

        return $this;

    }

    /**
     * Check if the key has the right grants.<br />
     * If No grant filter is required it returns true
     *
     * @param $role string
     * @param $tm_key Array
     *
     * @return bool
     */
    protected function _hasRightGrants( $tm_key, $role ){

        /*
         * No filtering required
         */
        if ( empty( $this->_required_grant ) ){
            return true;
        }

        if( $this->_required_grant == 'rw' ) {
            $has_required_grants = $tm_key[ self::$GRANTS_MAP[ $role ][ 'r' ] ] == true;
            /**
             * Using the logic OR allows us to take all the possible keys.
             * this means what if we want to select the keys that are readable AND writable,
             * then we must apply two filters in sequence: one with R flag, the other with the W flag.
             **/
            $has_required_grants = $has_required_grants && $tm_key[ self::$GRANTS_MAP[ $role ][ 'w' ] ] == true;
        } else {
            $has_required_grants = $tm_key[ self::$GRANTS_MAP[ $role ][ $this->_required_grant ] ] == true;
        }

        return $has_required_grants;

    }

    /**
     * Check if the key is of the right type.<br />
     * If No type filter is required it returns true
     *
     * @param $tm_key Array
     *
     * @return bool
     */
    protected function _isTheRightType( $tm_key ){

        /*
         * No filtering required
         */
        if( empty( $this->_type ) ){
            return true;
        }

        $_type = true;
        foreach( $this->_type as $type ){
            //trim for not well formatted required types
            //    Ex: "tm , glos " instead of "tm,glos"
            $_type = $_type && ( $tm_key[ trim($type) ] == true );
        }

        return $_type;

    }

}