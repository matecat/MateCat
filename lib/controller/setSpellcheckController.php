<?php

#include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';
include_once INIT::$UTILS_ROOT . '/SpellChecker/SpellCheckFactory.php';

class setSpellcheckController extends ajaxController {

    private $__postInput = null;

    public function __construct() {
        parent::__construct();

        $filterArgs = array(
            'word'     => array( 'filter' => FILTER_UNSAFE_RAW ),
            'slang'     => array( 'filter' => FILTER_SANITIZE_STRING,
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
        $spellCheck->setLanguageCode( $this->__postInput->slang );

        $this->result['error'] = $spellCheck->dictAddWord( $this->__postInput->word );

    }


}