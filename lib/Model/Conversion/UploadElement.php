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

class UploadElement implements ArrayAccess {

    use ArrayAccessTrait;
    use RecursiveArrayCopy;

    public function __construct( array $array_params = [] ) {
        if ( $array_params != null ) {
            foreach ( $array_params as $property => $value ) {
                $this->$property = $value;
            }
        }
    }

    public function __set( $name, $value ) {
        $this->$name = $value;
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function __get( $name ) {
        if ( !property_exists( $this, $name ) ) {
            return null;
        }

        return $this->$name;
    }

    public function getArrayCopy(): array {
        return $this->toArray();
    }

}