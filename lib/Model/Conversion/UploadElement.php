<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 13/08/25
 * Time: 15:57
 *
 */

namespace Model\Conversion;

use ArrayAccess;
use Model\DataAccess\ArrayAccessTrait;
use Model\DataAccess\RecursiveArrayCopy;
use stdClass;

/**
 * @property string $name
 * @property string $type
 * @property string $tmp_name
 * @property string $error
 * @property int $size
 * @property string $file_path
 */
class UploadElement extends stdClass implements ArrayAccess
{
    use ArrayAccessTrait;
    use RecursiveArrayCopy;

    /**
     * @param array $array_params Optional map of property names to values to hydrate on construction.
     */
    public function __construct(array $array_params = [])
    {
        if ($array_params != null) {
            foreach ($array_params as $property => $value) {
                $this->$property = $value;
            }
        }
    }

    /**
     * Dynamically sets any property on this element.
     *
     * @param string $name  Property name.
     * @param mixed  $value Property value.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->$name = $value;
    }

    /**
     * Dynamically retrieves a property, returning null if it does not exist.
     *
     * @param string $name Property name.
     *
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        if (!property_exists($this, $name)) {
            return null;
        }

        return $this->$name;
    }

    /**
     * Returns a plain array copy of all properties on this element.
     *
     * @return array
     */
    public function getArrayCopy(): array
    {
        return $this->toArray();
    }


    /**
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return property_exists($this, $name);
    }

}