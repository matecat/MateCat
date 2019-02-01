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

class StandardPHToMateCatCustomPH extends AbstractHandler {

    public function transform( $segment ) {

        if ( preg_match( '|</ph>|s', $segment ) ) {
            preg_match_all( '|<(ph id=["\'](.*?)["\'].*?)>(.*?)<(/ph)>|', $segment, $phTags, PREG_SET_ORDER );
            foreach ( $phTags as $group ) {
                $segment = preg_replace(
                        '/' . preg_quote( $group[ 0 ], '/' ) . '/',
                        '<ph id="__mtc_' . $this->getPipeline()->getNextId() . '" x-orig="' . base64_encode( $group[0] ) . '" equiv-text="base64:' .
                            base64_encode( htmlentities( $group[ 3 ], ENT_NOQUOTES | 16 /* ENT_XML1 */, 'UTF-8', true ) ) .
                        '"/>',
                        $segment,
                        1
                );
            }
        }

        preg_match_all( '/ph id\s*=\s*[\'"]((?!__mtc_).*?)[\'"] equiv-text\s*?=\s*?(["\'])(?!base64:)(.*?)\2/', $segment, $html, PREG_SET_ORDER );
        foreach ( $html as $tag_attribute ) {
            //replace subsequent elements excluding already encoded
            $segment = str_replace( $tag_attribute[ 0 ], 'ph id="' . $tag_attribute[ 1 ] . '" equiv-text="base64:' . base64_encode( $tag_attribute[ 3 ] ) . "\"", $segment );
        }

        return $segment;

    }


}


