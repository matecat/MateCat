<?php


namespace SubFiltering\Filters;


use SubFiltering\Commons\AbstractHandler;
use SubFiltering\Filters\Html\CallbacksHandler;
use SubFiltering\Filters\Html\HtmlParser;

class PhCounter extends AbstractHandler {

    use CallbacksHandler;

    protected $counter = 0;

    protected function _finalizeHTMLTag( $buffer ) {
        if( strpos( $buffer, '<ph' ) !== false  ){
            $this->counter++;
        }
        return $buffer;
    }

    protected function _fixWrongBuffer( $buffer ) {
        return $buffer;
    }

    protected function _isTagValid( $buffer ) {
       return true;
    }

    protected function _finalizePlainText( $buffer ) {
        return $buffer;
    }

    protected function _finalizeScriptTag( $buffer ) {
        return $buffer;
    }

    public function getCount(){
        return $this->counter;
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