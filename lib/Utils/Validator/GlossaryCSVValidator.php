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

        foreach ($headers as $index => $header){

            $isFirst = $index === 0;
            $isLast = $index === (count($headers)-1);
            $prev = !$isFirst ? Utils::trimAndLowerCase($headers[$index-1]) : null;
            $next = !$isLast ? Utils::trimAndLowerCase($headers[$index+1]) : null;
            $nextSibling = (!$isLast and isset($headers[$index+2])) ? Utils::trimAndLowerCase($headers[$index+2]) : null;
            $header = Utils::trimAndLowerCase($header);

            if(!$this->validateSingleHeader($header, $isFirst, $isLast, $prev, $next, $nextSibling)){
                $this->addError('Invalid header');

                return false;
            }
        }

        return true;
    }

    /**
     * @return array
     */
    private function allowedHeadingKeys()
    {
        return [
            'forbidden',
            'domain',
            'subdomain',
            'definition',
        ];
    }

    /**
     * @return array
     */
    private function allowedLanguagesSpecifications()
    {
        return [
            'notes',
            'example of use',
        ];
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
            $allowedLanguages[] = Utils::trimAndLowerCase($lang['rfc3066code']);
        }

        return $allowedLanguages;
    }

    /**
     * @param string $header
     * @param bool   $isFirst
     * @param bool   $isLast
     * @param null   $prev
     * @param null   $next
     * @param null   $nextSibling
     *
     * @return bool
     */
    private function validateSingleHeader( $header, $isFirst, $isLast, $prev = null, $next = null, $nextSibling = null)
    {
        if(in_array($header, $this->allowedHeadingKeys())){
            return true;
        }

        if(in_array($header, $this->allowedLanguages())){

            // cannot be last element
            if($isLast){
                return false;
            }

            // next element is a valid language
            if(in_array($next, $this->allowedLanguages())){
                return true;
            }

            // next element (or next+1) is a language specific instructions
            if(in_array($next, $this->allowedLanguagesSpecifications()) or in_array($nextSibling, $this->allowedLanguagesSpecifications())){
                return true;
            }

            return false;
        }

        if(in_array($header, $this->allowedLanguagesSpecifications())){

            // cannot be first element
            if($isFirst){
                return false;
            }

            // can be the last element
            if($isLast){
                return true;
            }

            // if prev element is a language or language specific instructions
            if(in_array($prev, $this->allowedLanguages()) or in_array($prev, $this->allowedLanguagesSpecifications())){
                return true;
            }

            // if next element is a language or language specific instructions
            if(in_array($next, $this->allowedLanguages()) or in_array($next, $this->allowedLanguagesSpecifications())){
                return true;
            }
        }

        return false;
    }
}