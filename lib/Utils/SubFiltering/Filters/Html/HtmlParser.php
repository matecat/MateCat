<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 15.30
 *
 */

namespace SubFiltering\Filters\Html;

use ReflectionException;
use ReflectionMethod;
use RuntimeException;

/**
 * Class HtmlToPh
 *
 * Based on the code https://github.com/ericnorris/striptags
 * Rewritten/Improved and Changed for PHP
 *
 * @author  domenico domenico@translated.net / ostico@gmail.com
 * @package SubFiltering
 *
 * @method _isTagValid( string $buffer )
 * @method _finalizeHTMLTag( string $buffer )
 * @method _fixWrongBuffer( string $buffer )
 * @method _finalizeScriptTag( string $buffer )
 * @method _finalizePlainText( string $plain_text_buffer )
 */
class HtmlParser {

    const STATE_PLAINTEXT = 0;
    const STATE_HTML      = 1;
    const STATE_COMMENT   = 2;
    const STATE_JS_CSS    = 3;

    /**
     * @var CallbacksHandler
     */
    protected $callbacksHandler;

    public function registerCallbacksHandler( $class ) {
        //check: $class must use CallbacksHandler trait
        if ( !in_array( CallbacksHandler::class, class_uses( $class ) ) ) {
            throw new RuntimeException( "Class passed to " . __METHOD__ . " must use " . CallbacksHandler::class . " trait." );
        }
        $this->callbacksHandler = $class;
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     * @throws ReflectionException
     */
    public function __call( $name, $arguments ) {

        //Reflection to allow protected/private methods to be set as callback
        $reflector = new ReflectionMethod( $this->callbacksHandler, $name );
        if ( !$reflector->isPublic() ) {
            $reflector->setAccessible( true );
        }

        return $reflector->invoke( $this->callbacksHandler, $arguments[ 0 ] );

    }

    public function transform( $segment ) {

        $originalSplit = preg_split( '//u', $segment, -1, PREG_SPLIT_NO_EMPTY );

//        $strippedSplit = preg_split( '//u', str_replace( [ "<", ">" ], "", $segment ), -1, PREG_SPLIT_NO_EMPTY );
//        if ( $originalSplit == $strippedSplit ) {
//            return $segment;
//        }

        $state             = static::STATE_PLAINTEXT;
        $html_buffer       = '';
        $plain_text_buffer = '';
        $depth             = 0;
        $in_quote_char     = '';
        $output            = '';

        foreach ( $originalSplit as $idx => $char ) {

            if ( $state == static::STATE_PLAINTEXT ) {
                switch ( $char ) {
                    case '<':
                        $state             = static::STATE_HTML;
                        $html_buffer       .= $char;
                        $output            .= $this->_finalizePlainText( $plain_text_buffer );
                        $plain_text_buffer = '';
                        break;

                    default:
                        $plain_text_buffer .= $char;
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

                        if ( in_array( substr( $html_buffer, 0, 6 ), [ '<scrip', '<style' ] ) ) {
                            $html_buffer .= $char;
                            $state       = static::STATE_JS_CSS;
                            break;
                        }

                        // this is closing the tag in tag_buffer
                        $in_quote_char = '';
                        $state         = static::STATE_PLAINTEXT;
                        $html_buffer   .= $char;

                        if ( $this->_isTagValid( $html_buffer ) ) {
                            $output .= $this->_finalizeHTMLTag( $html_buffer );
                        } else {
                            $output .= $this->_fixWrongBuffer( $html_buffer );
                        }

                        $html_buffer = '';
                        break;

                    case '"':
                    case '\'':
                        // catch both single and double quotes

                        if ( $char == $in_quote_char ) {
                            $in_quote_char = '';
                        } else {
                            $in_quote_char = ( !empty( $in_quote_char ) ? $in_quote_char : $char );
                        }

                        $html_buffer .= $char;
                        break;

                    case '-':
                        if ( $html_buffer == '<!-' ) {
                            $state = static::STATE_COMMENT;
                        }

                        $html_buffer .= $char;
                        break;

                    case ' ': //0x20, is a space
                    case '\n':
                        if ( $html_buffer === '<' ) {
                            $state       = static::STATE_PLAINTEXT; // but we work in XML text, so encode it
                            $output      .= $this->_fixWrongBuffer( '< ' );
                            $html_buffer = '';

                            break;
                        }

                        $html_buffer .= $char;
                        break;

                    default:
                        $html_buffer .= $char;
                        break;
                }
            } elseif ( $state == static::STATE_COMMENT ) {
                switch ( $char ) {
                    case '>':
                        $html_buffer .= $char;

                        if ( substr( $html_buffer, -3 ) == '-->' ) {
                            // close the comment
                            $state       = static::STATE_PLAINTEXT;
                            $output      .= $this->_finalizeScriptTag( $html_buffer );
                            $html_buffer = '';
                        }

                        break;

                    default:
                        $html_buffer .= $char;
                        break;
                }
            } elseif ( $state == static::STATE_JS_CSS ) {
                switch ( $char ) {
                    case '>':
                        $html_buffer .= $char;

                        if ( in_array( substr( $html_buffer, -6 ), [ 'cript>', 'style>' ] ) ) {
                            // close the comment
                            $state       = static::STATE_PLAINTEXT;
                            $output      .= $this->_finalizeScriptTag( $html_buffer );
                            $html_buffer = '';
                        }

                        break;

                    default:
                        $html_buffer .= $char;
                        break;
                }
            }
        }

        //HTML Partial, add wrong HTML to preserve string content
        if ( !empty( $html_buffer ) ) {
            $output .= $this->_fixWrongBuffer( $html_buffer );
        }

        //string ends with plain text, so no state change is triggered at the end of string
        if ( '' !==  $plain_text_buffer and null !== $plain_text_buffer  ) {
            $output .= $this->_finalizePlainText( $plain_text_buffer );
        }

        return $output;

    }

}