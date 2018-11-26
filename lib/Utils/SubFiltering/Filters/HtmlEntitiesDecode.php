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

class HtmlEntitiesDecode extends AbstractHandler {

    /**
     * @param $segment
     *
     * @return string
     */
    public function transform( $segment ){
        return html_entity_decode( $segment, ENT_QUOTES | ENT_XML1, 'UTF-8' );
    }

}