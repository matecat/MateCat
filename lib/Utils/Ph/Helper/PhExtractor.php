<?php

namespace Ph\Helper;

use Ph\Models\PhAnalysisModel;

class PhExtractor {

    /**
     * @param $string
     *
     * @return array
     */
    public static function extractToMap( $string ) {

        $regex = "<ph id\s*=\s*[\"']mtc_[0-9]+[\"'] equiv-text\s*=\s*[\"']base64:([^\"']+)[\"']\s*/>";

        preg_match_all( $regex, $string, $phMap, PREG_SET_ORDER );

        return isset($phMap[0]) ? $phMap : [];
    }
}