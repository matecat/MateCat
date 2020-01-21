<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 14/01/20
 * Time: 18:28
 *
 */

namespace SubFiltering\Filters\Html;

trait CallbacksHandler {

    abstract protected function _finalizeHTMLTag( $buffer );

    abstract protected function _fixWrongBuffer( $buffer );

    abstract protected function _isTagValid( $buffer );

    abstract protected function _finalizePlainText( $buffer );

    abstract protected function _finalizeScriptTag( $buffer );

}