<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 18.47
 *
 */

namespace SubFiltering\Filters;

use SubFiltering\Commons\AbstractHandler;
use SubFiltering\Commons\Constants;

class EncodeToRawXML extends AbstractHandler {

    public function transform( $segment ) {

        //placehold for all XML entities
        $segment = preg_replace( '/&(lt;|gt;|amp;|quot;|apos;)/', '##_ent_$1_##', $segment );

        // handling &#10; (new lien feed)
        // prevent to convert it to \n
        $segment = preg_replace( '/&(#10;|#x0A;)/', '##_ent_0A_##', $segment );

        // handling &#13; (carriage return)
        // prevent to convert it to \r
        $segment = preg_replace( '/&(#13;|#x0D;)/', '##_ent_0D_##', $segment );

        //decode all html entities found and re-encode in the right way
        $segment = htmlspecialchars(
                html_entity_decode( $segment, ENT_NOQUOTES, 'UTF-8' ),
                ENT_NOQUOTES | ENT_XML1, 'UTF-8', false
        );

        //restore entities
        $segment = preg_replace( '/##_ent_(lt;|gt;|amp;|quot;|apos;)_##/', '&$1', $segment );

        //Substitute 4(+)-byte characters from a UTF-8 string to htmlentities
        $segment = preg_replace_callback( '/([\xF0-\xF7]...)/s',  [ 'CatUtils', 'htmlentitiesFromUnicode' ], $segment );

        // now convert the real &nbsp;
        $segment = str_replace( Constants::nbspPlaceholder, \CatUtils::unicode2chr( 0Xa0 ), $segment );

        // handling &#10;
        if (strpos($segment, '##_ent_0D_##') !== false) {
            $segment = str_replace('##_ent_0D_##', '&#13;', $segment);
        }

        // handling &#13;
        if (strpos($segment, '##_ent_0A_##') !== false) {
            $segment = str_replace('##_ent_0A_##', '&#10;', $segment);
        }

        //encode all not valid XML entities
        $segment = preg_replace( '/&(?!lt;|gt;|amp;|quot;|apos;|#[x]{0,1}[0-9A-F]{1,7};)/', '&amp;', $segment );

        return $segment;
    }

}