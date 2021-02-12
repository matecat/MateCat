<?php


namespace Ph\Helper;


use Ph\Models\PhAnalysisModel;

class PhReplacer {

    /**
     * @param PhAnalysisModel $model
     * @param string          $phContent
     * @param string          $base64Value
     *
     * @return PhAnalysisModel
     */
    public static function replaceOriginalContentFromBase64Decoded( PhAnalysisModel $model, $phContent, $base64Value ) {
        $value   = base64_decode( $base64Value );
        $replace = str_replace( $phContent, $value, $model->getAfter() );
        $model->setAfter( $replace );

        return $model;
    }

    /**
     * @param PhAnalysisModel $model
     * @param string          $phContent
     * @param string          $value
     *
     * @return PhAnalysisModel
     */
    public static function replaceOriginalContentFromPlainContent( PhAnalysisModel $model, $phContent, $value ) {
        $replace = str_replace( $phContent, $value, $model->getAfter() );
        $model->setAfter( $replace );

        return $model;
    }
}