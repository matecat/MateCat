<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 05/05/25
 * Time: 17:56
 *
 */

namespace Model\Analysis\Constants;

use RuntimeException;

abstract class AbstractConstants implements ConstantsInterface {

    protected static array $forValue = [];

    /**
     * @param string $name
     *
     * @return string
     * @throws RuntimeException
     */
    public static function validate( string $name ): string {

        if ( !array_key_exists( $name, static::$forValue ) ) {
            throw new RuntimeException( "Invalid match type: " . $name );
        }

        return $name;

    }

    public static function forValue(): array {
        return static::$forValue;
    }

}