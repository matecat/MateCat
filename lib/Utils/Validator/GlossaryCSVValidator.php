<?php

namespace Validator;

use Files\CSV;
use Utils;
use Validator\Contracts\AbstractValidator;
use Validator\Contracts\ValidatorObject;

class GlossaryCSVValidator extends AbstractValidator {

    /**
     * @inheritDoc
     */
    public function validate( ValidatorObject $object ) {

        if(!$object instanceof GlossaryCSVValidatorObject ){
            throw new \Exception('Object given is not a valid instance of GlossaryCSVValidatorObject');
        }

        $headers = CSV::headers($object->csv);
        $headerString = '';

        foreach ($headers as $index => $header){
            $headerString .= Utils::trimAndLowerCase($header);
        }

        $allowedLanguages = $this->allowedLanguages();

        // 1. Validate languages
        if($this->validateLanguages($headers) === false){
            return false;
        }

        $allowedLanguagesRegex = implode("|", $allowedLanguages);

        // 2. Validate structure
        preg_match_all('/^(forbidden)?(domain)?(subdomain)?(definition)?((('.$allowedLanguagesRegex.')((notes)?(example of use)?){2,})+$)/', $headerString, $headerMatches);

        if(empty($languageMatches[0])){

            $this->errors[] = 'The order of the headers is incorrect, please change it to the one set out in <a href="https://guides.matecat.com/glossary-file-format" target="_blank">this support article</a>.';

            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    private function allowedLanguages()
    {
        $allowedLanguages = [];

        $file = \INIT::$UTILS_ROOT . '/Langs/supported_langs.json';
        $string = file_get_contents( $file );
        $langs = json_decode( $string, true );

        foreach ($langs['langs'] as $lang){
            $rfc3066code = Utils::trimAndLowerCase($lang['rfc3066code']);

            $allowedLanguages[] = $rfc3066code;
        }

        return $allowedLanguages;
    }

    /**
     * @param $headers
     * @return bool
     */
    private function validateLanguages($headers)
    {
        $skipKeys = [
            "forbidden",
            "domain",
            "subdomain",
            "definition",
            "notes",
            "example of use"
        ];

        $headersLowerCase = [];
        foreach ($headers as $header){
            $headersLowerCase[] = Utils::trimAndLowerCase($header);
        }

        $languages = array_diff($headersLowerCase, $skipKeys);
        $allowedLanguages = $this->allowedLanguages();

        if(count($languages) < 2){
            $this->errors[] = 'Minimum two language matches';

            return false;
        }

        foreach ($languages as $language){
            if(!in_array(Utils::trimAndLowerCase($language), $allowedLanguages)){

                $error = (strpos($language, '_') !== false) ? 'The column header '.$language.' contains an underscore, please replace it with a dash for the file to be valid for import. Ex: it_IT -> it-iT' : $language . ' is not a valid column header, you can find the correct column headers <a href="https://guides.matecat.com/glossary-file-format" target="_blank">here</a>.';
                $this->errors[] = $error;

                return false;
            }
        }
    }
}