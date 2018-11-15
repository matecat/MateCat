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

class LtGtDoubleEncode extends AbstractHandler {

    public function transform( $segment ) {
        $segment = str_replace("&lt;", "&amp;lt;", $segment);
        $segment = str_replace("&gt;", "&amp;gt;", $segment);
        return $segment;
    }

}