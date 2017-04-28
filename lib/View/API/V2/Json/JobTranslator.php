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

        $translatorJson = [
                'email'                 => $jTranslatorsStruct->email,
                'added_by'              => (int)$jTranslatorsStruct->added_by,
                'delivery_date'         => $jTranslatorsStruct->delivery_date,
                'delivery_timestamp'    => strtotime( $jTranslatorsStruct->delivery_date ),
                'source'                => $jTranslatorsStruct->source,
                'target'                => $jTranslatorsStruct->target,
                'id_translator_profile' => $jTranslatorsStruct->id_translator_profile,
                'user'                  => null
        ];

        if( !empty( $jTranslatorsStruct->id_translator_profile ) ){
            $translatorJson[ 'user' ] = User::renderItem( $jTranslatorsStruct->getUser() );
        }

        return $translatorJson;

    }

}