<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 16.17
 *
 */

namespace SubFiltering\Filters;


use SubFiltering\Commons\AbstractHandler;
use SubFiltering\Commons\Constants;

class RestoreXliffTagsForView extends AbstractHandler {

    public function transform( $segment ) {

        $segment = preg_replace_callback( '/' . Constants::LTPLACEHOLDER . '(.*?)' . Constants::GTPLACEHOLDER . '/u',
                function ( $matches ) {
                    $_match = base64_decode( $matches[ 1 ] );
                    return Constants::LTPLACEHOLDER . $_match . Constants::GTPLACEHOLDER;
                },
                $segment
        ); //base64 decode of the tag content to avoid unwanted manipulation

        preg_match_all( '/equiv-text\s*?=\s*?(["\'])(?!base64:)(.*?)\1/', $segment, $html, PREG_SET_ORDER );
        foreach ( $html as $tag_attribute ) {
            //replace subsequent elements excluding already encoded
            $segment = str_replace( $tag_attribute[ 0 ], 'equiv-text="base64:' . base64_encode( $tag_attribute[ 2 ] ) . "\"", $segment );
        }

        $segment = str_replace( Constants::LTPLACEHOLDER, "&lt;", $segment );
        $segment = str_replace( Constants::GTPLACEHOLDER, "&gt;", $segment );

        return $segment;

    }

}