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

class EntitiesDecode extends AbstractHandler {

    public function transform( $segment ) {

        //replace all outgoing spaces couples to a space and a &nbsp; so they can be displayed to the browser
        $segment = preg_replace( '/[ ]{2}/', "&nbsp; ", $segment );
        $segment = preg_replace( '/[ ]$/', "&nbsp;", $segment );

        $segment = html_entity_decode( $segment, ENT_NOQUOTES | 16 /* ENT_XML1 */, 'UTF-8' );

        $segment = preg_replace_callback( '/([\xF0-\xF7]...)/s', function ( $match ) {
            return "&#" . \CatUtils::fastUnicode2ord( $match[ 1 ] ) . ";";
        }, $segment );

        return $segment;

    }

}