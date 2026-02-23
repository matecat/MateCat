<?php


namespace Controller\API\App;


use Controller\Abstracts\KleinController;
use Matecat\Locales\Languages;

class SupportedLanguagesController extends KleinController
{


    public function index(): void
    {
        $lang_handler = Languages::getInstance();
        $languages_array = $lang_handler->getEnabledLanguages();
        $this->response->json(
            array_values($languages_array)
        );
    }

}