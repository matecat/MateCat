<?php

namespace Validator;

use Engine;
use Engines_MMT;
use Exception;

class MMTGlossaryValidator
{
    /**
     * @param $mmtGlossaries
     * @param $uid
     * @throws Exception
     */
    public static function validate($mmtGlossaries)
    {
        $mmtGlossariesArray = json_decode($mmtGlossaries, true);

        if(!is_array($mmtGlossariesArray)){
            throw new Exception("mmt_glossaries is not a valid JSON");
        }

        foreach ($mmtGlossariesArray as $mmtGlossary){
            if(!isset($mmtGlossary['ignore_glossary_case'])){
                throw new Exception("`ignore_glossary_case` key not found in `mmt_glossaries` JSON");
            }

            if(!isset($mmtGlossary['id_mmt_glossary'])){
                throw new Exception("`id_mmt_glossary` key not found in `mmt_glossaries` JSON");
            }

            if(!is_bool($mmtGlossary['ignore_glossary_case'])){
                throw new Exception("`ignore_glossary_case` is not boolean in `mmt_glossaries` JSON");
            }

            if(!is_int($mmtGlossary['id_mmt_glossary'])){
                throw new Exception("`id_mmt_glossary` is not integer in `mmt_glossaries` JSON");
            }
        }
    }
}