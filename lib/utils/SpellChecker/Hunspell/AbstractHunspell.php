<?php

abstract class AbstractHunspell {

    protected $_encoding = "en_US.utf-8";

    protected $_raw;
    protected $_hunspellVersion;

    protected $_spellCheckEnabled = false;

    protected $_dictionariesRoot;
    protected $_langCode;
    protected $_dictionaryPath;
    protected $_globalDictionary;
    protected $_personalDictionaryPath;

    protected $_suggestions = array();

    // Identify a 'miss'. See [man hunspell]
    protected $_matcher = '/^(?P<type>[&#]{1})\s(?P<original>[\w\.]+)\s(?P<count>\d+)(\s(?P<offset>\d+):\s(?P<misses>.*))?$/u';

    protected $_response = array();

    /**
     * @param array $config
     * <pre>
     *      array(
     *          'lang_code'       => 'en_GB',
     *          'dictionary_path' => /home/matecat/cattool/lib/SpellChecker/dictionaries/en_GB/
     * </pre>
     */
    public function __construct( $config = array() ) {

        mb_internal_encoding("UTF-8");
        mb_regex_encoding('UTF-8');

        if( isset( $config['lang_code'] ) ){
            $langCode = $config['lang_code'];
        } else {
            $langCode = "en_GB";
        }

        if( isset( $config['dictionary_path'] ) ){
          $path = $config['dictionary_path'];
        } else {
          $path = realpath(dirname(__FILE__) . '/../') . DIRECTORY_SEPARATOR . "dictionaries/";
        }

        $this->setDictionariesPath( $path );
        $this->setLanguageCode( $langCode );

    }

    /**
     * Request a suggestion to Hunspell and return a fetched resource
     *
     * @param $string
     *
     * @return mixed
     */
    abstract public function getSuggestions( $string );

    /**
     * Request a spell check to Hunspell and return a fetched resource
     *
     * @param $string
     *
     * @return array|mixed
     */
    abstract public function getIncorrectWords( $string );

    /**
     * Add a word to dictionary
     *
     * @param $word
     *
     * @return mixed|string
     */
    abstract public function dictAddWord( $word );

    abstract protected function _sendCommand( $command, $string );

    protected function _isSpellCheckingEnabled(){
        return $this->_spellCheckEnabled;
    }

    public function enableSpellCheck(){
        $this->_spellCheckEnabled = true;
    }

    public function disableSpellCheck(){
        $this->_spellCheckEnabled = false;
    }

    /**
     * Do not use when socket enabled, no effect
     *
     * @param $path string Path of Dictionary file.
     */
    public function setDictionariesPath( $path ){
        $this->_dictionariesRoot = $path;
    }

    public function setLanguageCode( $langCode ){
        $this->_langCode = $langCode;
        $this->_setGlobalDictionary();
        $this->setPersonalDictionaryPath();
    }

    protected function _setGlobalDictionary(){
        $this->_dictionaryPath = $this->_dictionariesRoot . $this->_langCode;
        $this->_globalDictionary = $this->_dictionariesRoot . $this->_langCode . "/" . $this->_langCode;
    }

    /**
     * Do not use when socket enabled, no effect
     *
     * @param $fullyQualifiedName string Path of Personal Dictionary file.
     */
    public function setPersonalDictionaryPath( $fullyQualifiedName = null ){
        if( empty($fullyQualifiedName) ){
            $fullyQualifiedName = $this->_dictionariesRoot . $this->_langCode . "/personal-" . $this->_langCode . ".dic";
        }
        $this->_personalDictionaryPath = $fullyQualifiedName;
    }

    /**
     * Method to fetch hunspell output when requested a suggestion
     *
     * @param $string
     *
     * @throws Exception
     */
    protected function _parseSuggestions( $string ) {

        if ( $this->_raw == null ) {
            throw new Exception( "Can't parse: no response." );
        }

        // Split the response into lines.
        $lines = explode( "\n", $this->_raw );

        // First item is the version #
        $this->_hunspellVersion = $lines[ 0 ];

        unset( $lines[ 0 ] );

        foreach ( $lines as $line ) {

            preg_match( $this->_matcher, $line, $matches );

            if ( count( $matches ) == 0 ) {
                continue;
            }

            if ( $matches[ 'type' ] == "&" ) {

                $this->_response[ $matches[ 'original' ] ] = array(
                    //"original" => $matches[ 'original' ],
                    //"count"    => $matches[ 'count' ],
                    "offset"   => $matches[ 'offset' ],
                    "misses"   => explode( ", ", $matches[ 'misses' ] )
                );

            } else {

                $this->_response[ $matches[ 'original' ] ] = array(
                    //"original" => $matches[ 'original' ],
                    "offset"   => $matches[ 'count' ]
                );

            }

        }

        foreach ( $this->_response as $_original => $word ) {
            if ( isset( $this->_response[ $_original ][ 'misses' ] ) ) {
                //unrecognized word
                $this->_suggestions[][$_original] = $this->_response[ $_original ];
                //$this->_suggestions[][$_original] = $this->_response[ $_original ][ 'misses' ];
            } else if ( !isset( $this->_response[ $_original ][ 'misses' ] ) ) {
                //unrecognized word and no suggestions
                $this->_suggestions[][$_original] = $this->_response[ $_original ];
                //$this->_suggestions[][$_original] = array();
            } else {
                //NO OP
                //Correct word
            }
        }

    }

    /**
     * Method to fetch hunspell output when requested a word check
     *
     */
    protected function _parseLint(){

        if ( $this->_raw != null ) {
            $incorrectWords = explode( "\n", $this->_raw );
            array_pop( $incorrectWords );
            foreach( $incorrectWords as $_word ){
               $this->_response[ ] = $_word;
            }

        } else {
            $this->_response[ ] = array();
        }

    }

    protected function _output( $result ) {
        return $result;
    }

}
