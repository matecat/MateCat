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

        // Filters BUG, segmentation on HTML, we should never get this at this level ( Should be fixed, anyway we try to cover )
        $segment = $this->placeHoldBrokenHTML( $segment );

        $double_encode = false;
        if( strpos( $segment , Constants::LTPLACEHOLDER ) !== false ){

            $decXliff = new RestoreXliffTagsContent();
            $test_segment = $decXliff->transform( $segment );

            if( preg_match_all( RestoreSubFilteredPhToHtml::matchPhRegexp, $test_segment, $matches, PREG_SET_ORDER ) ){
                //here we are in a sub-filtered layer with HTML ?? check
                foreach( $matches as $match ){
                    if( strpos( base64_decode( $match[ 1 ] ), "&lt;" ) !== false ) {
                        $double_encode = true; //yes there is html in ph tags
                        break;
                    }
                }
            }

        }

        if ( $double_encode ) {
            //encode twice all html entities
            $segment = htmlspecialchars( $segment, ENT_QUOTES | ENT_XML1, 'UTF-8', true );
        }

        $segment = htmlspecialchars( $segment, ENT_QUOTES | ENT_XML1, 'UTF-8', true );

        //normal control characters must be converted to entities
        $segment = str_replace(
                [ "\r\n", "\r", "\n", "\t", "", ],
                [
                        '&#13;&#10;',
                        '&#13;',
                        '&#10;',
                        '&#09;',
                        '&#157;',
                ], $segment );

        //Substitute 4(+)-byte characters from a UTF-8 string to htmlentities
        $segment = preg_replace_callback( '/([\xF0-\xF7]...)/s',  [ 'CatUtils', 'htmlentitiesFromUnicode' ], $segment );

        // now convert the real &nbsp;
        $segment = str_replace( Constants::nbspPlaceholder, \CatUtils::unicode2chr( 0Xa0 ), $segment );

        // Filters BUG, segmentation on HTML, we should never get this at this level ( Should be fixed, anyway we try to cover )
        $segment = $this->resetBrokenHTML( $segment );

        return $segment;

    }

    private function placeHoldBrokenHTML( $segment ){

        //Filters BUG, segmentation on HTML, we should never get this at this level ( Should be fixed, anyway we try to cover )
        //    &lt;a href="/help/article/1381?
        $this->brokenHTML = false;

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