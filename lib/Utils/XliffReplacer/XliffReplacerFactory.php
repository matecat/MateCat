<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 20/08/19
 * Time: 18.17
 *
 */

namespace XliffReplacer;

class XliffReplacerFactory {

    public static function getInstance( array $fileType, &$data, &$transUnits, $target_lang ){
        if( $fileType[ 'proprietary_short_name' ] != 'trados' ){
            return new XliffSAXTranslationReplacer( $data, $transUnits, $target_lang );
        } else {
            return new SdlXliffSAXTranslationReplacer( $data, $transUnits, $target_lang );
        }
    }

}