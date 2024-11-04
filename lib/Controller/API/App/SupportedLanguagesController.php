<?php


namespace API\App;


use API\Commons\KleinController;
use Langs\Languages;

class SupportedLanguagesController extends KleinController {


    public function index() {
        $lang_handler = Languages::getInstance();
        $languages_array = $lang_handler->getEnabledLanguages() ;
        $this->response->json(
                array_values( $languages_array )
        );
    }

}