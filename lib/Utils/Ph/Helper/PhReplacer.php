<?php


namespace Ph\Helper;


use Ph\Models\PhAnalysisModel;

class PhReplacer {

    /**
     * @param PhAnalysisModel $model
     * @param string          $phContent
     * @param string          $base64Value
     *
     * @return string
     */
    public static function replaceOriginalContent( PhAnalysisModel $model, $phContent, $base64Value ) {
        $value = base64_decode( $base64Value );
        $html  = [
                htmlentities( "<" ) . $phContent . htmlentities( ">" ),
                "<" . $phContent . ">"
        ];

        return str_replace( $html, $value, $model->getAfter() );
    }
}