<?php

//namespace Features\ReviewImproved\Controller;


header( "Cache-Control: no-store, no-cache, must-revalidate" );  // HTTP/1.1
header( "Cache-Control: post-check=0, pre-check=0", false );
header( "Pragma: no-cache" );


class peeViewController extends viewController{

    public function __construct() {
        parent::__construct();
        parent::makeTemplate( "pee.html" );

    }

    public function doAction() {

        $this->dataLangStats=array();
        $this->languageStats = getLanguageStats();


        for ($i = 0; $i <= $this->languageStats.length+1; $i++) {
            $curr=array( "source"=>$this->languageStats[$i]['source'], "target"=>$this->languageStats[$i]['target'], "pee"=>(($this->languageStats[$i]['total_post_editing_effort'])/($this->languageStats[$i]['job_count'])), "totalwordPEE"=>$this->languageStats[$i]['total_word_count']);
            array_push($this->dataLangStats,$curr);
        }



    }

    public function setTemplateVars() {
        $this->template->dataLangStats   = json_encode($this->dataLangStats);

    }


}