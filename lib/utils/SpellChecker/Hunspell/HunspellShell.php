<?php

include_once 'AbstractHunspell.php';
include_once INIT::$UTILS_ROOT . '/SpellChecker/service/Process.php';

class HunspellShell extends AbstractHunspell {

    /**
     * @param $string
     *
     * @return mixed
     */
    public function getSuggestions( $string ) {

        if( !$this->_isSpellCheckingEnabled() ){
            return $this->_output( array() );
        }

        $command = "hunspell -i 'utf-8' -d '" . escapeshellcmd( $this->_globalDictionary ) . "' -p '" . escapeshellcmd( $this->_personalDictionaryPath ) . "'";

        $this->_sendCommand( $command , $string );

        $this->_parseSuggestions( $string );

        return $this->_output( $this->_suggestions );

    }

    /**
     * @param $string
     *
     * @return array|mixed
     */
    public function getIncorrectWords($string) {

        if( !$this->_isSpellCheckingEnabled() ){
            return $this->_output( array() );
        }

        $command = "hunspell -i 'utf-8' -d " . escapeshellcmd( $this->_globalDictionary ) . " -p " . escapeshellcmd( $this->_personalDictionaryPath ) . " -l ";

        $this->_sendCommand( $command, $string );

        $this->_parseLint();

        return $this->_output( $this->_response );

    }

    /**
     * @param $word
     *
     * @return mixed|string
     */
    public function dictAddWord( $word ) {

        if( !$this->_isSpellCheckingEnabled() ){
            return $this->_output( false );
        }

        //Load custom dictionaries does not work from php shell_exec environment, so add to general dictionary
        //$res = file_put_contents( $this->_personalDictionaryPath, ( $word . "\n" ), FILE_APPEND );
        $res = file_put_contents( $this->_globalDictionary . ".dic", ( $word . "\n" ), FILE_APPEND );
        return $this->_output( 'OK' );

    }

    /**
     * @param $command
     * @param $string
     */
    protected function _sendCommand( $command, $string ) {

        $process = new Process( $command, $this->_globalDictionary );
        $process->write( $string );
        $this->_raw = $process->read();
        $process->close();

    }

}