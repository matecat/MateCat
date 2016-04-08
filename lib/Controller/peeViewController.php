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

        $this->dataLangStats[] = array(
                "source"       => null,
                "target"       => null,
                "pee"          => 0,
                "totalwordPEE" => null,
                "peeSigma"     => 0
        );

        $languageStats = getLanguageStats();

        foreach ( $languageStats as $k => $value ) {
            $this->dataLangStats[] = array(
                    "source"       => $value[ 'source' ],
                    "target"       => $value[ 'target' ],
                    "pee"          => ( ( $value[ 'total_post_editing_effort' ] ) / ( $value[ 'job_count' ] ) ),
                    "totalwordPEE" => $value[ 'total_word_count' ],
                    "peeSigma"     => $value[ 'pee_sigma' ]
            );
        }

    }

    public function setTemplateVars() {
        $this->template->dataLangStats = json_encode( $this->dataLangStats );
    }


}