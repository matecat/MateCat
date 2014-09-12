<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/09/14
 * Time: 13.35
 */


class TmKeyManagement_TmKeyStruct extends stdClass {

    /**
     * @var int This key is for tm. 0 or 1
     */
    public $tm;

    /**
     * @var int This key is for glossary. 0 or 1
     */
    public $glos;

    /**
     * A flag that indicates whether the key has been created by the owner or not
     * @var int 0 or 1
     */
    public $owner;

    /**
     * @var string The key's name
     */
    public $name;

    /**
     * @var string
     */
    public $key;

    /**
     * @var int Read grant. 0 or 1
     */
    public $r;

    /**
     * @var int Write grant. 0 or 1
     */
    public $w;

    /**
     * @param array|null $params An associative array with the following keys:<br/>
     * <pre>
     *          tm      : int     - 0 or 1. Tm key
     *          glos    : int     - 0 or 1. Glossary key
     *          owner   : boolean
     *          key     : string
     *          r       : int     - 0 or 1. Read privilege
     *          w       : int     - 0 or 1. Write privilege
     * </pre>
     */
    public function __construct( $params = null ) {
        if ( $params != null ) {
            foreach ( $params as $property => $value ) {
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
        return (array) $this;
    }
} 