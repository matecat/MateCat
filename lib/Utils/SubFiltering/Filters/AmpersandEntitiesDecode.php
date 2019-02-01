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

class AmpersandEntitiesDecode extends AbstractHandler {

    /**
     * @param $segment
     *
     * @return string
     *               &(amp;(lt|gt|amp;);
     */
    public function transform( $segment ){
        return str_replace( '&amp;', '&', $segment );
    }

}