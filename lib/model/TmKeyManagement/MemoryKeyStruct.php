<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 29/09/14
 * Time: 18.07
 */

/**
 * Class MemoryKeyStruct<br>
 * This class represents a row in the table memory_keys.
 */
class TmKeyManagement_MemoryKeyStruct extends stdClass implements DataAccess_IDaoStruct {

    /**
     * @var integer The group's ID
     */
    public $gid;

    /**
     * @var integer The user's ID
     */
    public $uid;

    /**
     * @var integer The owner's ID
     */
    public $owner_uid;

    /**
     * @var bool Group Read grants, the atomic value
     */
    public $r;

    /**
     * @var bool Group Write grants, the atomic value
     */
    public $w;

    /**
     * @var TmKeyManagement_TmKeyStruct
     */
    public $tm_key;

    public function __construct( Array $array_params = array() ) {
        if ( $array_params != null ) {
            foreach ( $array_params as $property => $value ) {
                $this->$property = $value;
            }
        }
    }

    public function __set( $name, $value ) {
        if ( !property_exists( $this, $name ) ) {
            throw new DomainException( 'Unknown property ' . $name );
        }
    }

    /**
     * Converts the current object into an associative array
     * @return array
     */
    public function toArray() {
        $result = (array)$this;

        if ( $this->tm_key !== null ) {
            $result[ 'tm_key' ] = $this->tm_key->toArray();
        }

        return $result;
    }

} 