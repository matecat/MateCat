<?php

class peeViewController extends viewController {

    /**
     * Data field filled to display in the template
     * @var array
     */
    protected $dataLangStats = array();

    public function __construct() {
        parent::__construct();
        parent::makeTemplate( "pee.html" );

    }

    public function doAction() {

        $languageStats = getLanguageStats();
        include_once 'lib/Utils/Langs/Languages.php';
        $instance= Langs_Languages::getInstance();

        if( !empty( $languageStats ) ){
            $this->dataLangStats = array();
        } else {
            $this->dataLangStats[] = array(
                    "source"       => null,
                    "target"       => null,
                    "pee"          => 0,
                    "totalwordPEE" => null
            );
        }

        foreach ( $languageStats as $k => $value ) {
            $this->dataLangStats[] = array(
                    "source"       => $instance->getLocalizedName($value[ 'source' ]),
                    "target"       => $instance->getLocalizedName($value[ 'target' ]),
                    "pee"          => $value[ 'total_post_editing_effort' ],
                    "totalwordPEE" => number_format($value[ 'total_word_count' ],0,",","."),
                    "payable_rate" => Analysis_PayableRates::pee2payable($value[ 'total_post_editing_effort' ])
            );
        }

    }

    public function setTemplateVars() {
        $this->template->dataLangStats = json_encode( $this->dataLangStats );
    }
}