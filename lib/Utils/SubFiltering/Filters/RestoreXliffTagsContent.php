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

class RestoreXliffTagsContent extends AbstractHandler {

    public function transform( $segment ) {

        $segment = preg_replace_callback( '/' . Constants::LTPLACEHOLDER . '(.*?)' . Constants::GTPLACEHOLDER . '/u',
                function ( $matches ) {
                    $_match = base64_decode( $matches[ 1 ] );
                    return Constants::LTPLACEHOLDER . $_match . Constants::GTPLACEHOLDER;
                },
                $segment
        ); //base64 decode of the tag content to avoid unwanted manipulation

        return $segment;

    }

}