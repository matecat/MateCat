<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 12/08/24
 * Time: 18:29
 *
 */

namespace Filters\DTO;

use ReflectionClass;

trait DefaultTrait {

    /**
     * @return array
     */
    public static function default(): array {

        $default = [];

        $instance = new self();
        $clazz    = new ReflectionClass( $instance );

        $properties = $clazz->getProperties();

        foreach ( $properties as $property ) {
            $property->setAccessible( true );
            $default[ $property->getName() ] = $property->getValue( $instance );
        }

        return $default;

    }

}