<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 16.23
 *
 */

namespace SubFiltering\Filters;

use SubFiltering\Commons\AbstractHandler;

class UnicodeToEntities extends AbstractHandler {

    public function transform( $segment ) {

        $segment = preg_replace_callback( '/([\xF0-\xF7]...)/s', function ( $match ) {
            return "&#" . \CatUtils::fastUnicode2ord( $match[ 1 ] ) . ";";
        }, $segment );

        return $segment;

    }

}