<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 15.30
 *
 */

namespace SubFiltering\Filters;

use SubFiltering\Commons\AbstractHandler;

/**
 * Class HtmlToPh
 *
 * Based on the code https://github.com/ericnorris/striptags
 * Rewritten/Improved and Changed for PHP
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * @package SubFiltering
 *
 */
class HtmlToPhToLayer2 extends HtmlToPh {

    protected function _fixWrongBuffer( $buffer ){
        $buffer = str_replace( "<", "&amp;lt;", $buffer );
        $buffer = str_replace( ">", "&amp;gt;", $buffer );
        return $buffer;
    }

}