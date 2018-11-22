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

class SpacesToNBSPForView extends AbstractHandler {

    public function transform( $segment ) {

        //replace all outgoing spaces couples to a space and a &nbsp; so they can be displayed to the browser
        $segment = preg_replace( '/[ ]{2}/', "&nbsp; ", $segment );
        $segment = preg_replace( '/[ ]$/', "&nbsp;", $segment );

        return $segment;

    }

}