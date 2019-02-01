<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 17.09
 *
 */

namespace SubFiltering\Filters;


use SubFiltering\Commons\AbstractHandler;

class LtGtDoubleDecode extends AbstractHandler {

    public function transform( $segment ) {
        $segment = str_replace("&amp;lt;", "&lt;", $segment);
        $segment = str_replace("&amp;gt;", "&gt;", $segment);
        return $segment;
    }

}