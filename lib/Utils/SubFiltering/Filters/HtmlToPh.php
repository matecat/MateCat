<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 15.30
 *
 */

namespace SubFiltering\Filters;

use SubFiltering\Commons\AbstractHandler;
use SubFiltering\Commons\Constants;
use SubFiltering\Filters\Html\CallbacksHandler;
use SubFiltering\Filters\Html\HtmlParser;

/**
 * Class HtmlToPh
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * @package SubFiltering
 *
 */
class HtmlToPh extends AbstractHandler {

    use CallbacksHandler;

    /**
     * @param $buffer
     *
     * @return mixed
     */
    protected function _finalizePlainText( $buffer ) {
        return $buffer;
    }

    /**
     * @param $buffer
     *
     * @return string
     */
    protected function _finalizeHTMLTag( $buffer ){

        //decode attributes by locking <,> first
        //because a html tag has it's attributes encoded and here we get lt and gt decoded but not other parts of the string
        // Ex:
        // incoming string : <a href="/users/settings?test=123&amp;amp;ciccio=1" target="_blank">
        // this should be:   <a href="/users/settings?test=123&amp;ciccio=1" target="_blank"> with only one ampersand encoding
        //
        $buffer = str_replace( [ '<', '>' ], [ '#_lt_#', '#_gt_#' ], $buffer );
        $buffer = html_entity_decode( $buffer, ENT_NOQUOTES | 16 /* ENT_XML1 */, 'UTF-8' );
        $buffer = str_replace( [ '#_lt_#', '#_gt_#' ], [ '<', '>' ], $buffer );

        return $this->_finalizeTag( $buffer );

    }

    /**
     * @param $buffer
     *
     * @return string
     */
    protected function _finalizeTag( $buffer ){
        return '<ph id="__mtc_' . $this->getPipeline()->getNextId() . '" equiv-text="base64:' . base64_encode( htmlentities( $buffer, ENT_NOQUOTES | 16 /* ENT_XML1 */ ) ) . '"/>';
    }

    /**
     * @param $buffer
     *
     * @return mixed
     */
    protected function _fixWrongBuffer( $buffer ){
        $buffer = str_replace( "<", "&lt;", $buffer );
        $buffer = str_replace( ">", "&gt;", $buffer );
        return $buffer;
    }

    /**
     * @param $buffer
     *
     * @return string
     */
    protected function _finalizeScriptTag( $buffer ){
        return $this->_finalizeTag( $buffer );
    }

    /**
     * This is meant to cover the case when strip_tags fails because of a string like these
     *
     * " test 3<4 and test 2>5 " <-- becomes --> " test 35 "
     *
     * Only tags should be converted here
     *
     * @param $buffer
     *
     * @return bool
     */
    protected function _isTagValid( $buffer ) {

        /*
         * accept tags start with:
         * - starting with / ( optional )
         * - NOT starting with a number
         * - containing [a-zA-Z0-9\-\._] at least 1
         * - ending with a letter a-zA-Z0-9 or a quote "' or /
         *
         */
        if ( preg_match( '#<[/]{0,1}(?![0-9]+)[a-z0-9\-\._]+?(?:\s[:_a-z]+=.+?)?\s*[\/]{0,1}>#is', $buffer ) ){
//            if( is_numeric( substr( $buffer, -2, 1 ) ) && !preg_match( '#<[/]{0,1}[h][1-6][^>]*>#is', $buffer ) ){ //H tag are an exception
//                //tag can not end with a number
//                return false;
//            }

            //this case covers when filters create an xliff tag inside an html tag:
            //EX:
            //original:  &lt;a href=\"<x id="1">\"&gt;
            //  <a href=\"##LESSTHAN##eCBpZD0iMSIv##GREATERTHAN##\">
            if( strpos( $buffer, Constants::LTPLACEHOLDER ) !== false || strpos( $buffer, Constants::GTPLACEHOLDER ) !== false ){
                return false;
            }

            return true;
        }

        return false;

    }

    /**
     * @param $segment
     *
     * @return string
     */
    public function transform( $segment ) {

        $parser = new HtmlParser();
        $parser->registerCallbacksHandler( $this );
        return $parser->transform( $segment );

    }

}