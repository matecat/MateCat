<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/08/25
 * Time: 19:03
 *
 */

namespace Utils\Engines\Lara;

class HeaderField {

    private string $key;
    private string $value;

    public function __construct( string $key, string $value ) {
        $this->key   = $key;
        $this->value = $value;
    }

    public function getKey(): string {
        return $this->key;
    }

    public function getValue(): string {
        return $this->value;
    }

    public function getArrayCopy(): array {
        return [ $this->key => $this->value ];
    }

}