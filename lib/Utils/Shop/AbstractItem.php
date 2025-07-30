<?php
/**
 * Created by PhpStorm.
 */

/**
 * Abstract parent for Items to use with Shop_Cart class
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 17/04/14
 * Time: 16.44
 *
 */
abstract class Shop_AbstractItem extends ArrayObject implements Shop_ItemInterface {

    /**
     * This is the real storage for cart items
     *
     * These fields are mandatory to use with Class Shop_Cart
     *
     * <pre>
     * $__storage = array(
     *      '_id_type_class' => null,
     *      'id'             => null,
     *      'quantity'       => null,
     *      'price'          => null
     * );
     * </pre>
     *
     * @var array
     */
    protected $__storage = [
            '_id_type_class' => null,
            'id'             => null,
            'quantity'       => null,
            'price'          => null,
    ];

    /**
     * Class Constructor
     *
     */
    public function __construct() {
        parent::__construct();

        $value = get_class( $this );

        //prepare the structure to accept  the value
        //this key is mandatory for Cart Class because of $calledClass::getInflate( $storage );
        $this->__storage[ '_id_type_class' ] = $value;

        //set the value
        $this->offsetSet( '_id_type_class', $value );

        /*
         * Prepare the storage object by using self::$__storage keys definitions
         */
        foreach ( $this->__storage as $key => $value ) {
            $this->offsetSet( $key, $value );
        }

    }

    /**
     * Return an array copy of the storage content
     *
     * @return array
     */
    public function getStorage() {
        return $this->getArrayCopy();
    }

    /**
     * Offset to set (PHP 5 >= 5.0.0)
     *
     * Only items defined in the concrete Item class will be added and/or permitted
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
     * @see  $__storage
     *
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     */
    public function offsetSet( $offset, $value ) {

        if ( empty( $offset ) ) {
            throw new LogicException( "Can not assign a value to an EMPTY key." );
        }

        if ( !array_key_exists( $offset, $this->__storage ) ) {
            throw new DomainException( "Field $offset does not exists in " . __CLASS__ . " structure." );
        }

        $value = filter_var( $value, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES );
        parent::offsetSet( $offset, $value );

    }

    /**
     * Offset to unset (PHP 5 &gt;= 5.0.0)
     *
     * Only items defined in the concrete Item class will be accepted
     *
     * @param mixed $offset <p>
     *                      The offset to unset.
     *                      </p>
     *
     * @return void
     *
     * @throws DomainException
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @see  $__storage
     *
     */
    public function offsetUnset( $offset ) {
        if ( array_key_exists( $offset, $this->__storage ) ) {
            parent::offsetUnset( $offset );
        } else {
            throw new DomainException( "Field $offset does not exists in " . __CLASS__ . " structure." );
        }
    }

} 