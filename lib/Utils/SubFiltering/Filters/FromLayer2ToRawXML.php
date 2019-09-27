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

/**
 * Class FromLayer2ToRawXML
 * Same as EncodeToRawXML but from strings coming from layer 2
 *
 * @package SubFiltering\Filters
 */
class FromLayer2ToRawXML extends AbstractHandler {

    private $brokenHTML = false;

    public function transform( $segment ) {
        //placehold for all XML entities
        $segment = preg_replace( '/&(lt;|gt;|amp;|quot;|apos;)/', '##_ent_$1_##', $segment );

        // handling &#13;
        $segment = str_replace( "\r", '##_ent_0D_##', $segment );

        // handling &#13;
        $segment = str_replace( "\n", '##_ent_0A_##', $segment );


        // Filters BUG, segmentation on HTML, we should never get this at this level ( Should be fixed, anyway we try to cover )
        $segment = $this->placeHoldBrokenHTML( $segment );

        //decode all html entities found and re-encode in the right way
        $segment = htmlspecialchars(
                html_entity_decode( $segment, ENT_NOQUOTES, 'UTF-8' ),
                ENT_QUOTES | ENT_XML1, 'UTF-8', false
        );

        //Substitute 4(+)-byte characters from a UTF-8 string to htmlentities
        $segment = preg_replace_callback( '/([\xF0-\xF7]...)/s',  [ 'CatUtils', 'htmlentitiesFromUnicode' ], $segment );

        // now convert the real &nbsp;
        $segment = str_replace( Constants::nbspPlaceholder, \CatUtils::unicode2chr( 0Xa0 ), $segment );

        //encode all not valid XML entities
        $segment = preg_replace( '/&(?!lt;|gt;|amp;|quot;|apos;|#[x]{0,1}[0-9A-F]{1,7};)/', '&amp;', $segment );

        //encode all not valid XML entities
        $segment = preg_replace( '/&(?!amp;|quot;|apos;)/', '&amp;$1', $segment );

        // Filters BUG, segmentation on HTML, we should never get this at this level ( Should be fixed, anyway we try to cover )
        $segment = $this->resetBrokenHTML( $segment );

        //restore entities
        $segment = preg_replace( '/##_ent_(lt;|gt;|amp;|quot;|apos;)_##/', '&$1', $segment );

        // handling &#10;
        if (strpos($segment, '##_ent_0A_##') !== false) {
            $segment = str_replace('##_ent_0A_##', '&#10;', $segment);
        }

        // handling &#13;
        if (strpos($segment, '##_ent_0D_##') !== false) {
            $segment = str_replace('##_ent_0D_##', '&#13;', $segment);
        }

        return $segment;

    }

    private function placeHoldBrokenHTML( $segment ){

        //Filters BUG, segmentation on HTML, we should never get this at this level ( Should be fixed, anyway we try to cover )
        //    &lt;a href="/help/article/1381?
        $this->brokenHTML = false;

        //This is from Layer 2 to Layer 0
        if( stripos( $segment, '##_ent_lt;_##a href="' ) ){
            $segment = str_replace( '##_ent_lt;_##a href="', '##__broken_lt__##a href=##__broken_quot__##', $segment );
            $this->brokenHTML = true;
        }

        //This is from Layer 2 to Layer 1
        if( stripos( $segment, '<a href="' ) ){
            $segment = str_replace( '<a href="', '##__broken_lt__##a href=##__broken_quot__##', $segment );
            $this->brokenHTML = true;
        }

        return $segment;

    }

    private function resetBrokenHTML( $segment ){

        // Reset
        if( $this->brokenHTML ){
            $segment = str_replace( '##__broken_lt__##a href=##__broken_quot__##', '&lt;a href="', $segment );
        }

        return $segment;

    }

}