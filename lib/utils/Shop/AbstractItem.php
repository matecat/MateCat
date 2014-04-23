<?php
/**
 * Created by PhpStorm.
 * User: domenico domenico@translated.net / ostico@gmail.com
 * Date: 17/04/14
 * Time: 16.44
 * 
 */

abstract class Shop_AbstractItem extends ArrayObject implements Shop_ItemInterface {

    /**
     * These fields are mandatory to use with Class Shop_Cart
     *
     * $__storage = array(
     *      '_id_type_class' => null,
     *      'id'             => null,
     *      'quantity'       => null,
     *      'price'          => null,
     * );
     *
     * @var array
     */
    protected $__storage = array(
            '_id_type_class' => null,
            'id'             => null,
            'quantity'       => null,
            'price'          => null,
    );

    public function __construct(){
        parent::__construct();

        $value = get_class( $this );

        //prepare the structure to accept  the value
        //this key is mandatory for Cart Class because of $calledClass::getInflate( $storage );
        $this->__storage[ '_id_type_class' ] = $value;

        //set the value
        $this->offsetSet( '_id_type_class', $value );

        foreach( $this->__storage as $key => $value ){
            $this->offsetSet( $key, $value );
        }

    }

    public function getStorage(){
        return $this->getArrayCopy();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset <p>
     *                      The offset to assign the value to.
     *                      </p>
     * @param mixed $value  <p>
     *                      The value to set.
     *                      </p>
     *
     * @return void
     *
     * @throws LogicException/DomainException
     */
    public function offsetSet( $offset, $value ) {

        if ( empty( $offset ) ) {
            throw new LogicException( "Can not assign a value to an EMPTY key." );
        }

        if ( !array_key_exists( $offset, $this->__storage ) ) {
            throw new DomainException( "Field $offset does not exists in " . __CLASS__ . " structure." );
        }

        $value = filter_var( $value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES );
        parent::offsetSet( $offset, $value );

    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset <p>
     *                      The offset to unset.
     *                      </p>
     *
     * @return void
     *
     * @throws DomainException
     */
    public function offsetUnset( $offset ) {
        if ( array_key_exists( $offset, $this->__storage ) ) {
            parent::offsetUnset( $offset );
        } else {
            throw new DomainException( "Field $offset does not exists in " . __CLASS__ . " structure." );
        }
    }

} 