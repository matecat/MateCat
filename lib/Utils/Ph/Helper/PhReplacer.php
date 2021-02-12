<?php


namespace Ph\Helper;


use Ph\Models\PhAnalysisModel;

class PhReplacer {

    /**
     * @param PhAnalysisModel $model
     * @param array           $ph
     *
     * @return string
     */
    public static function replaceOriginalContent(PhAnalysisModel $model, array $ph = [])
    {
        $value  = base64_decode( $ph[ 1 ] );

        $html   = [
                htmlentities( "<" ) . $ph[0] . htmlentities( ">" ),
                "<".$ph[0].">"
        ];

        return str_replace( $html, $value,  $model->getAfter());
    }
}