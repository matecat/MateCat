<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 18.47
 *
 */

namespace SubFiltering;


class EncodeToRawXML extends AbstractChannelHandler {

    public function transform( $segment ) {

        $segment = htmlspecialchars(
                html_entity_decode( $segment, ENT_NOQUOTES, 'UTF-8' ),
                ENT_NOQUOTES, 'UTF-8', true
        );

        //Substitute 4(+)-byte characters from a UTF-8 string to htmlentities
        $segment = preg_replace_callback( '/([\xF0-\xF7]...)/s', 'CatUtils::htmlentitiesFromUnicode', $segment );

        //replace all incoming &nbsp; ( \xA0 ) with normal spaces ( \x20 ) as we accept only ##$_A0$##
        $segment = str_replace( \CatUtils::unicode2chr( 0Xa0 ), " ", $segment );

        // now convert the real &nbsp;
        $segment = str_replace( Constants::nbspPlaceholder, \CatUtils::unicode2chr( 0Xa0 ), $segment );

        //encode all not valid XML entities
        $segment = preg_replace( '/&(?!lt;|gt;|amp;|quot;|apos;|#[x]{0,1}[0-9A-F]{1,7};)/', '&amp;', $segment );

        return $segment;

    }

}