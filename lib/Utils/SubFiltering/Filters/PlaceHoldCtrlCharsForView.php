<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 17.20
 *
 */

namespace SubFiltering\Filters;


use SubFiltering\Commons\AbstractHandler;
use SubFiltering\Commons\Constants;

class PlaceHoldCtrlCharsForView extends AbstractHandler {

    public function transform( $segment ) {

        $segment = str_replace( "\r\n", Constants::crlfPlaceholder, $segment );
        $segment = str_replace( "\n", Constants::lfPlaceholder, $segment );
        $segment = str_replace( "\r", Constants::crPlaceholder, $segment ); //x0D character
        $segment = str_replace( "\t", Constants::tabPlaceholder, $segment ); //x09 character
        $segment = preg_replace( '/[\x{c2}]{0,1}\x{a0}/u', Constants::nbspPlaceholder, $segment ); //xA0 character ( NBSP )

        return $segment;

    }

}