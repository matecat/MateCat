<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 06/11/24
 * Time: 11:17
 *
 */

namespace Model\DataAccess;

use ReflectionObject;
use ReflectionProperty;

trait RecursiveArrayCopy {

    /**
     * Returns an array of public attributes for the struct.
     * If $mask is provided, the resulting array will include
     * only the specified keys.
     *
     * This method is useful in conjunction with PDO execute, where only
     * a subset of the attributes may be required to be bound to the query.
     *
     * @param $mask ?array a mask for the keys to return
     *
     * @return array
     *
     */
    public function toArray( array $mask = null ): array {

        $attributes       = [];
        $reflectionClass  = new ReflectionObject( $this );
        $publicProperties = $reflectionClass->getProperties( ReflectionProperty::IS_PUBLIC );
        foreach ( $publicProperties as $property ) {
            if ( !empty( $mask ) ) {
                if ( !in_array( $property->getName(), $mask ) ) {
                    continue;
                }
            }
            $attributes[ $property->getName() ] = $property->getValue( $this );
        }

        return $attributes;

    }

}