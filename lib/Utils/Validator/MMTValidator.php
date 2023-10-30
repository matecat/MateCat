<?php

namespace Validator;

use Exception;

class MMTValidator
{
    /**
     * Example:
     * {"glossaries": [1, 2, 3, 4], "ignore_glossary_case": true }
     *
     * @param $mmtGlossaries
     * @param $uid
     * @throws Exception
     */
    public static function validateGlossary($mmtGlossaries)
    {
        $mmtGlossariesArray = json_decode($mmtGlossaries, true);

        if(!is_array($mmtGlossariesArray)){
            throw new Exception("mmt_glossaries is not a valid JSON");
        }

        if(!isset($mmtGlossariesArray['ignore_glossary_case'])){
            throw new Exception("`ignore_glossary_case` key not found in `mmt_glossaries` JSON");
        }

        if(!is_bool($mmtGlossariesArray['ignore_glossary_case'])){
            throw new Exception("`ignore_glossary_case` is not boolean in `mmt_glossaries` JSON");
        }

        if(!is_array($mmtGlossariesArray['glossaries'])){
            throw new Exception("`glossaries` is not an array in `mmt_glossaries` JSON");
        }

        foreach ($mmtGlossariesArray['glossaries'] as $glossaryId){
            if(!is_int($glossaryId)){
                throw new Exception("`glossaries` array contains a non integer value in `mmt_glossaries` JSON");
            }
        }
    }
}