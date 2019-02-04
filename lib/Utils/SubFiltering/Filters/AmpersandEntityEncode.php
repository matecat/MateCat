<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 17.34
 *
 */

namespace SubFiltering\Filters;

use SubFiltering\Commons\AbstractHandler;

class AmpersandEntityEncode extends AbstractHandler {

    /**
     * @param $segment
     *
     * @return string
     */
    public function transform( $segment ){
        return str_replace( '&amp;', '&amp;amp;', $segment );
    }

}