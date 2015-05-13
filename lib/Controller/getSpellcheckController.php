<?php

include_once INIT::$UTILS_ROOT . '/SpellChecker/SpellCheckFactory.php';

class getSpellcheckController extends ajaxController {

    private $__postInput = null;

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
            'sentence' => array( 'filter' => FILTER_UNSAFE_RAW ),
            'lang'     => array( 'filter' => FILTER_SANITIZE_STRING,
                                 'flags'  => FILTER_FLAG_STRIP_LOW ),
            'password' => array( 'filter' => FILTER_SANITIZE_STRING,
                                 'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'token'    => array( 'filter' => FILTER_SANITIZE_STRING,
                                 'flags'  => FILTER_FLAG_STRIP_LOW ),
        );

        $this->__postInput = (object)filter_input_array( INPUT_POST, $filterArgs );

    }

    public function doAction() {

        $spellCheck = SpellCheckFactory::getInstance();
        $spellCheck->setLanguageCode( $this->__postInput->lang );

        //$this->__postInput->sentence = strip_tags( $this->__postInput->sentence );

        $specials = array('/\+/', '/\*/', '/#/', '/~/', '/@/', '/\^/');
        $replace_specials = array('\+', '\*', '\#', '\~', '\@', '\^');
        $sentence = preg_replace( $specials, $replace_specials, $this->__postInput->sentence );

        $this->result['result'] = $spellCheck->getSuggestions( $sentence );

    }


}