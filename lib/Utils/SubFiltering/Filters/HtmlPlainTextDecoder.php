<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 05/11/18
 * Time: 15.30
 *
 */

namespace SubFiltering\Filters;

use SubFiltering\Filters\Html\CallbacksHandler;

/**
 * Class HtmlPlainTextDecoder
 *
 * @author domenico domenico@translated.net / ostico@gmail.com
 * @package SubFiltering
 *
 */
class HtmlPlainTextDecoder extends HtmlToPh {

    use CallbacksHandler;

    /**
     * @param $buffer
     *
     * @return mixed
     */
    protected function _finalizePlainText( $buffer ) {
        return html_entity_decode( $buffer, ENT_NOQUOTES | 16 /* ENT_XML1 */, 'UTF-8' );
    }

    protected function _finalizeTag( $buffer ){
        return $buffer;
    }

    /**
     * @param $buffer
     *
     * @return string
     */
    protected function _finalizeHTMLTag( $buffer ){
        return $this->_finalizeTag( $buffer );
    }

}