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

/**
 * Class HtmlToPh
 *
 * Based on the code https://github.com/ericnorris/striptags
 * Rewritten/Improved and Changed for PHP
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * @package SubFiltering
 *
 */
class HtmlToPh extends AbstractHandler {

    const STATE_PLAINTEXT = 0;
    const STATE_HTML      = 1;
    const STATE_COMMENT   = 2;
    const STATE_JS_CSS    = 3;

    public function transform( $segment ) {

        $originalSplit = preg_split( '//u', $segment, -1, PREG_SPLIT_NO_EMPTY );
        $strippedSplit = preg_split( '//u', str_replace( [ "<", ">" ], "", $segment ), -1, PREG_SPLIT_NO_EMPTY );

        if ( $originalSplit == $strippedSplit ) {
            return $segment;
        }

        $state         = static::STATE_PLAINTEXT;
        $buffer        = '';
        $depth         = 0;
        $in_quote_char = '';
        $output        = '';

        foreach( $originalSplit as $idx => $char ) {

            if ( $state == static::STATE_PLAINTEXT ) {
                switch ( $char ) {
                    case '<':
                        $state      = static::STATE_HTML;
                        $buffer .= $char;
                        break;

                    default:
                        $output .= $char;
                        break;
                }
            } elseif ( $state == static::STATE_HTML ) {
                switch ( $char ) {
                    case '<':
                        // ignore '<' if inside a quote
                        if ( $in_quote_char ) {
                            break;
                        }

                        // we're seeing a nested '<'
                        $depth++;
                        break;

                    case '>':
                        // ignore '>' if inside a quote
                        if ( $in_quote_char ) {
                            break;
                        }

                        // something like this is happening: '<<>>'
                        if ( $depth ) {
                            $depth--;

                            break;
                        }

                        if( in_array( substr( $buffer, 0, 6 ), [ '<scrip', '<style' ] ) ){
                            $buffer .= $char;
                            $state         = static::STATE_JS_CSS;
                            break;
                        }

                        // this is closing the tag in tag_buffer
                        $in_quote_char = '';
                        $state         = static::STATE_PLAINTEXT;
                        $buffer    .= '>';

                        if ( $this->isTagValid( $buffer ) ){
                            $output .= '<ph id="__mtc_' . $this->getPipeline()->getNextId() . '" equiv-text="base64:' . base64_encode( htmlentities( $buffer, ENT_NOQUOTES | 16 /* ENT_XML1 */ ) ) . '"/>';
                        } else {
                            $output .= $this->_fixWrongBuffer( $buffer );
                        }

                        $buffer = '';
                        break;

                    case '"':
                    case '\'':
                        // catch both single and double quotes

                        if ( $char == $in_quote_char ) {
                            $in_quote_char = '';
                        } else {
                            $in_quote_char = ( !empty( $in_quote_char ) ? $in_quote_char : $char );
                        }

                        $buffer .= $char;
                        break;

                    case '-':
                        if ( $buffer == '<!-' ) {
                            $state = static::STATE_COMMENT;
                        }

                        $buffer .= $char;
                        break;

                    case ' ': //0x20, is a space
                    case '\n':
                        if ( $buffer === '<' ) {
                            $state      = static::STATE_PLAINTEXT; // but we work in XML text, so encode it
                            $output     .= $this->_fixWrongBuffer( '< ' );
                            $buffer = '';

                            break;
                        }

                        $buffer .= $char;
                        break;

                    default:
                        $buffer .= $char;
                        break;
                }
            } elseif ( $state == static::STATE_COMMENT ) {
                switch ( $char ) {
                    case '>':
                        $buffer .= $char;

                        if ( substr( $buffer, -3 ) == '-->' ) {
                            // close the comment
                            $state = static::STATE_PLAINTEXT;
                            $output .= '<ph id="__mtc_' . $this->getPipeline()->getNextId() . '" equiv-text="base64:' . base64_encode( htmlentities( $buffer, ENT_NOQUOTES | 16 /* ENT_XML1 */ ) ) . '"/>';
                            $buffer = '';
                        }

                        break;

                    default:
                        $buffer .= $char;
                        break;
                }
            } elseif ( $state == static::STATE_JS_CSS ) {
                switch ( $char ) {
                    case '>':
                        $buffer .= $char;

                        if ( in_array( substr( $buffer, -6 ), [ 'cript>', 'style>' ] ) ) {
                            // close the comment
                            $state = static::STATE_PLAINTEXT;
                            $output .= '<ph id="__mtc_' . $this->getPipeline()->getNextId() . '" equiv-text="base64:' . base64_encode( htmlentities( $buffer, ENT_NOQUOTES | 16 /* ENT_XML1 */ ) ) . '"/>';
                            $buffer = '';
                        }

                        break;

                    default:
                        $buffer .= $char;
                        break;
                }
            }
        }

        //HTML Partial, add wrong HTML to preserve string content
        if( !empty( $buffer ) ){
            $output .= $this->_fixWrongBuffer( $buffer );
        }

        return $output;

    }

    protected function _fixWrongBuffer( $buffer ){
        $buffer = str_replace( "<", "&lt;", $buffer );
        $buffer = str_replace( ">", "&gt;", $buffer );
        return $buffer;
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
    protected function isTagValid( $buffer ) {

        /*
         * accept tags start with:
         * - starting with / ( optional )
         * - NOT starting with a number
         * - containing [a-zA-Z0-9\-\._] at least 1
         * - ending with a letter a-zA-Z or a quote "' or /
         *
         */
        if ( preg_match( '#<[/]{0,1}(?![0-9]+)[a-zA-Z0-9\-\._]+?(?:\s[:A-Z_a-z]+=.+?)?\s*[\/]{0,1}>#', $buffer ) ){
            if( is_numeric( substr( $buffer, -2, 1 ) ) && !preg_match( '#<[/]{0,1}[hH][1-6][^>]*>#', $buffer ) ){ //H tag are an exception
                //tag can not end with a number
                return false;
            }
            return true;
        }

        return false;

    }

}