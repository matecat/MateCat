<?php
include_once 'AbstractHunspell.php';

class HunspellSocket extends AbstractHunspell {

    /**
     * @param $word
     *
     * @return array|mixed
     */
    public function getSuggestions( $word ) {
        // TODO: Implement getSuggestions() method.
        return array();
    }

    public function getIncorrectWords( $string ) {
        // TODO: Implement getIncorrectWords() method.
        return array();
    }

    /**
     * @param $word
     *
     * @return mixed|string
     */
    public function dictAddWord( $word ) {
        // TODO: Implement dictAddWord() method.
        return 'OK';
    }

    protected function _sendCommand( $command, $string ) {
        // TODO: Implement _sendCommand() method.
    }


}