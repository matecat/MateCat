<?php

use API\App\Json\PeeTableData;
use API\V2\Validators\WhitelistAccessValidator;

class peeViewController extends viewController {

    /**
     * Data field filled to display in the template
     * @var array
     */
    protected $dataLangStats = [];

    /**
     * @var array
     */
    protected $snapshots = [];

    private $lang_handler;

    public function __construct() {
        parent::__construct();
        parent::makeTemplate( "pee.html" );

        $this->lang_handler    = Langs_Languages::getInstance();

    }

    public function doAction() {

        ( new WhitelistAccessValidator( $this ) )->validate();

        $languageStats = ( new LanguageStats_LanguageStatsDAO() )->getLanguageStats( null );
        $this->snapshots = ( new LanguageStats_LanguageStatsDAO() )->getSnapshotDates();

        $format = new PeeTableData( $languageStats );
        $this->dataLangStats = $format->render()[ 'langStats' ];

    }

    public function setTemplateVars() {
        $this->template->dataLangStats = json_encode( $this->dataLangStats );

        $this->template->languages_array = $this->lang_handler->getEnabledLanguages( 'en' )  ;
        $this->template->languages_json = json_encode(  $this->lang_handler->getEnabledLanguages( 'en' ) ) ;

        $this->template->snapshots = $this->snapshots;
        $this->template->lastMonth = end( $this->snapshots )[ 'date' ];
    }
}