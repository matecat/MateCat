<?php


namespace API\V2;


use API\Commons\KleinController;

class SupportedLanguagesController extends KleinController {


    public function index() {
        $lang_handler = \Langs_Languages::getInstance();
        $languages_array = $lang_handler->getEnabledLanguages() ;
        $this->response->json(
                $languages_array
        );
    }

}