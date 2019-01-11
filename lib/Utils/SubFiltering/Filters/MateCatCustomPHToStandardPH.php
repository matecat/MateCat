<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 11/01/19
 * Time: 15.11
 *
 */

namespace SubFiltering\Filters;

use SubFiltering\Commons\AbstractHandler;

class MateCatCustomPHToStandardPH extends AbstractHandler {

    public function transform( $segment ) {

        //pipeline for restore PH tag of subfiltering to original encoded HTML
        preg_match_all( '|<ph id\s*=\s*["\']mtc_[0-9]+["\'] x-orig\s*=\s*["\']([^"\']+)["\'] equiv-text\s*=\s*["\']base64:[^"\']+["\']\s*\/>|siU', $segment, $html, PREG_SET_ORDER ); // Ungreedy
        foreach ( $html as $subfilter_tag ) {
            $segment = str_replace( $subfilter_tag[0], base64_decode( $subfilter_tag[ 1 ] ), $segment );
        }

        return $segment;

    }


}


