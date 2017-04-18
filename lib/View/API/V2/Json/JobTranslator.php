<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 21.55
 *
 */

namespace API\V2\Json;


use Translators\JobsTranslatorsStruct;

class JobTranslator {

    public function renderItem( JobsTranslatorsStruct $jTranslatorsStruct ) {
        return array(
                'email'                 => $jTranslatorsStruct->email,
                'added_by'              => $jTranslatorsStruct->added_by,
                'delivery_date'         => $jTranslatorsStruct->delivery_date,
                'source'                => $jTranslatorsStruct->source,
                'target'                => $jTranslatorsStruct->target,
                'id_translator_profile' => $jTranslatorsStruct->id_translator_profile
        );
    }

}