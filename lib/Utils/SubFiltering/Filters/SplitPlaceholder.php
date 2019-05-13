<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 02/05/2019
 * Time: 17:20
 */

namespace SubFiltering\Filters;

use SubFiltering\Commons\AbstractHandler;

class SplitPlaceholder extends AbstractHandler {
    public function transform( $segment ) {
        $segment = str_replace( \CatUtils::splitPlaceHolder, "", $segment );

        return $segment;
    }
}