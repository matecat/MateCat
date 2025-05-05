<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 05/05/25
 * Time: 17:54
 *
 */

namespace API\App\Json\Analysis\Constants;

use RuntimeException;

interface ConstantsInterface {

    public static function toInternalMatchTypeValue( string $match_type ): string;

    public static function toExternalMatchTypeValue( string $match_type ): string;

    /**
     * @param string $name
     *
     * @return string
     * @throws RuntimeException
     */
    public static function validate( string $name ): string;

    public static function getWorkflowType(): string;

    public static function forValue(): array;

}