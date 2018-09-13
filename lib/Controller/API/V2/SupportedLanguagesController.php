<?php


namespace API\V2;


class SupportedLanguagesController extends KleinController {


    public function index() {
        $lang_handler = \Langs_Languages::getInstance();
        $languages_array = $lang_handler->getEnabledLanguages() ;
        $this->response->json(
                $languages_array
        );
    }

}