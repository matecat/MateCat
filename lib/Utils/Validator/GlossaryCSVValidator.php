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

        preg_match_all('/('.$allowedLanguages.')/', $headerString, $languageMatches);

        if(empty($languageMatches[0]) or count($languageMatches[0]) < 2){

            $this->errors[] = 'Minimum two language matches';

            return false;
        }

        preg_match_all('/^(forbidden)?(domain)?(subdomain)?(definition)?((('.$allowedLanguages.')((notes)?(example of use)?){2,})+$)/', $headerString, $headerMatches);

        if(empty($languageMatches[0])){

            $this->errors[] = 'Incorrect header matches';

            return false;
        }

        return true;
    }


    /**
     * @return string
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
            $allowedLanguages[] = str_replace('-','_', $rfc3066code); // Allow also this format: en_US
        }

        return implode("|", $allowedLanguages);
    }
}