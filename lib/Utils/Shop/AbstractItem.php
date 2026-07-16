<?php
/**
 * Created by PhpStorm.
 */

namespace Utils\Shop;

use ArrayObject;
use LogicException;
use Model\DataAccess\UnknownPropertyException;
use RuntimeException;

/**
 * Abstract parent for Items to use with Cart class
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 17/04/14
 * Time: 16.44
 *
 */
/**
 * @extends ArrayObject<string, mixed>
 */
abstract class AbstractItem extends ArrayObject
{

    /**
     * This is the real storage for cart items
     *
     * These fields are mandatory to use with Class Cart
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
     * @var array<string, mixed>
     */
    protected array $__storage = [
        '_id_type_class' => null,
        'id' => null,
        'quantity' => null,
        'price' => null,
    ];

    /**
     * @param array<string, mixed> $storage
     *
     * @throws RuntimeException
     * @throws LogicException
     */
    public static function getInflate(array $storage): AbstractItem
    {
        $obj = new $storage['_id_type_class']();
        if (!$obj instanceof AbstractItem) {
            throw new RuntimeException('Invalid item class: ' . $storage['_id_type_class']);
        }
        foreach ($storage as $key => $value) {
            $obj->offsetSet($key, $value);
        }

        return $obj;
    }

    /**
     * @throws LogicException
     */
    public function __construct()
    {
        parent::__construct();

        $value = get_class($this);

        //prepare the structure to accept  the value
        //this key is mandatory for Cart Class because of $calledClass::getInflate( $storage );
        $this->__storage['_id_type_class'] = $value;

        //set the value
        $this->offsetSet('_id_type_class', $value);

        /*
         * Prepare the storage object by using self::$__storage keys definitions
         */
        foreach ($this->__storage as $key => $value) {
            $this->offsetSet($key, $value);
        }
    }

    /**
     * Return an array copy of the storage content
     *
     * @return array<string, mixed>
     */
    public function getStorage(): array
    {
        return $this->getArrayCopy();
    }

    /**
     * Offset to set (PHP 5 >= 5.0.0)
     *
     * Only items defined in the concrete Item class will be added and/or permitted
     *
     * @param mixed $key <p>
     *                      The offset to assign the value to.
     *                      </p>
     * @param mixed $value <p>
     *                      The value to set.
     *                      </p>
     *
     * @return void
     *
     * @throws UnknownPropertyException
     * @throws LogicException
     * @see  $__storage
     *
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        if (empty($key)) {
            throw new LogicException("Can not assign a value to an EMPTY key.");
        }

        if (!array_key_exists($key, $this->__storage)) {
            throw new UnknownPropertyException($key, __CLASS__);
        }

        $value = filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES);
        parent::offsetSet($key, $value);
    }

    /**
     * Offset to unset (PHP 5 &gt;= 5.0.0)
     *
     * Only items defined in the concrete Item class will be accepted
     *
     * @param mixed $key <p>
     *                      The offset to unset.
     *                      </p>
     *
     * @return void
     *
     * @throws UnknownPropertyException
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @see  $__storage
     *
     */
    public function offsetUnset(mixed $key): void
    {
        if (array_key_exists($key, $this->__storage)) {
            parent::offsetUnset($key);
        } else {
            throw new UnknownPropertyException($key, __CLASS__);
        }
    }

} 