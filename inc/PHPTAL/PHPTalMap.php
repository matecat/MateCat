<?php

use DataAccess\ArrayAccessTrait;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/06/25
 * Time: 12:39
 *
 */
class PHPTalMap implements ArrayAccess, JsonSerializable {

    use ArrayAccessTrait;

    private array $storage = [];

    public function __construct( array $values = [] ) {
        foreach ( $values as $key => $value ) {
            if ( is_array( $value ) ) {
                if ( is_numeric( $key ) ) {
                    $this->storage[] = new PHPTalMap( $value );
                } else {
                    $this->storage[ $key ] = new PHPTalMap( $value );
                }
            } else {
                $this->storage[ $key ] = $value;
            }
        }
    }

    public function __toString() {
        return json_encode( $this->storage );
    }

    public function jsonSerialize(): array {
        return $this->storage;
    }

}