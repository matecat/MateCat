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

        $languageStats = ( new LanguageStats_LanguageStatsDAO() )->getLanguageStats();
        $languages_instance = Langs_Languages::getInstance();

        if ( !empty( $languageStats ) ) {
            $this->dataLangStats = [];
        } else {
            $this->dataLangStats[] = [
                    "source"       => null,
                    "target"       => null,
                    "pee"          => 0,
                    "fuzzy_band"   => null,
                    "totalwordPEE" => null
            ];
        }

        foreach ( $languageStats as $k => $value ) {
            $this->dataLangStats[] = [
                    "source"       => $languages_instance->getLocalizedName( $value[ 'source' ] ),
                    "target"       => $languages_instance->getLocalizedName( $value[ 'target' ] ),
                    "pee"          => $value[ 'total_post_editing_effort' ],
                    "fuzzy_band"   => $value[ 'fuzzy_band' ],
                    "totalwordPEE" => number_format( $value[ 'total_word_count' ], 0, ",", "." ),
                    "payable_rate" => Analysis_PayableRates::pee2payable( $value[ 'total_post_editing_effort' ] )
            ];
        }

    }

    public function setTemplateVars() {
        $this->template->dataLangStats = json_encode( $this->dataLangStats );
    }
}