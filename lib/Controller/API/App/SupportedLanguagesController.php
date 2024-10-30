<?php


namespace API\App;


use API\Commons\KleinController;

class SupportedLanguagesController extends KleinController {


    public function index() {
        $lang_handler = \Langs_Languages::getInstance();
        $languages_array = $lang_handler->getEnabledLanguages() ;
        $this->response->json(
                array_values( $languages_array )
        );
    }

}