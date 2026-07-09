<?php


namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\ResponseAlreadySentException;
use Matecat\Locales\Languages;

class SupportedLanguagesController extends KleinController
{


    /**
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     */
    public function index(): void
    {
        $lang_handler = Languages::getInstance();
        $languages_array = $lang_handler->getEnabledLanguages();
        $this->response->json(
            array_values($languages_array)
        );
    }

}