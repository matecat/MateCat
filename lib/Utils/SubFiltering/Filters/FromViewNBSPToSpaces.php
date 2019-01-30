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

class FromViewNBSPToSpaces extends AbstractHandler {

    public function transform( $segment ) {

        //replace all outgoing spaces couples to a space and a &nbsp; so they can be displayed to the browser
        $segment = preg_replace( '/&nbsp;/', " ", $segment );

        //replace all incoming &nbsp; ( \xA0 ) with normal spaces ( \x20 ) as we accept only ##$_A0$##
        $segment = str_replace( \CatUtils::unicode2chr( 0Xa0 ), " ", $segment );

        return $segment;

    }

}