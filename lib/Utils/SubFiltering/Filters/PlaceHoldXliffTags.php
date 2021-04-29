<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 16.17
 *
 */

namespace SubFiltering\Filters;


use Matecat\XliffParser\Utils\HtmlParser;
use SubFiltering\Commons\AbstractHandler;
use SubFiltering\Commons\Constants;

class PlaceHoldXliffTags extends AbstractHandler {

    public function transform( $segment ) {

        // input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>

        //remove not existent </x> tags
        $segment = preg_replace( '|(</x>)|si', "", $segment );

        //$segment=preg_replace('|<(g\s*.*?)>|si', Constants::LTPLACEHOLDER."$1".Constants::GTPLACEHOLDER,$segment);
        $segment = preg_replace( '|<(g\s*id=["\']+.*?["\']+\s*[^<>]*?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );

        $segment = preg_replace( '|<(/g)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );

        $segment = preg_replace( '|<(x .*?/?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );
        $segment = preg_replace( '#<(bx[ ]{0,}/?|bx .*?/?)>#si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );
        $segment = preg_replace( '#<(ex[ ]{0,}/?|ex .*?/?)>#si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );
        $segment = preg_replace( '|<(bpt\s*.*?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );
        $segment = preg_replace( '|<(/bpt)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );
        $segment = preg_replace( '|<(ept\s*.*?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );
        $segment = preg_replace( '|<(/ept)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );
        $segment = preg_replace( '|<(ph .*?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );
        $segment = preg_replace( '|<(/ph)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );
        $segment = preg_replace( '|<(pc .*?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );
        $segment = preg_replace( '|<(/pc)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );
        $segment = preg_replace( '|<(it .*?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );
        $segment = preg_replace( '|<(/it)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );
        $segment = preg_replace( '|<(mrk\s*.*?)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );
        $segment = preg_replace( '|<(/mrk)>|si', Constants::LTPLACEHOLDER . "$1" . Constants::GTPLACEHOLDER, $segment );

        return preg_replace_callback( '/' . Constants::LTPLACEHOLDER . '(.*?)' . Constants::GTPLACEHOLDER . '/u',
                function ( $matches ) {
                    return Constants::LTPLACEHOLDER . base64_encode( $matches[ 1 ] ) . Constants::GTPLACEHOLDER;
                }, $segment
        ); //base64 of the tag content to avoid unwanted manipulation
    }
}