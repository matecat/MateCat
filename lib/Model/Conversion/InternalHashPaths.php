<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 30/05/25
 * Time: 17:51
 *
 */

namespace Conversion;

class InternalHashPaths {

    protected string $cacheHash;
    protected string $diskHash;

    public function __construct( array $array_params ) {
        if ( $array_params != null ) {
            foreach ( $array_params as $property => $value ) {
                $this->$property = $value;
            }
        }
    }

    public function getCacheHash(): string {
        return $this->cacheHash;
    }

    public function getDiskHash(): string {
        return $this->diskHash;
    }

    public function isEmpty(): bool {
        return empty( $this->cacheHash ) && empty( $this->diskHash );
    }

}