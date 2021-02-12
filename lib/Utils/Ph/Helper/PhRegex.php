<?php

namespace Ph\Helper;

class PhRegex {

    /**
     * @param $string
     *
     * @return array
     */
    public static function extractAll( $string ) {
        $regex = '/(<|&lt;)ph id\s*=\s*["\']mtc_[0-9]+["\'] equiv-text\s*=\s*["\']base64:([^"\']+)["\']\s*\/(>|&gt;)/';

        return self::getResults($regex, $string);
    }

    /**
     * @param $string
     * @param $base64Value
     *
     * @return array
     */
    public static function extractByContent($string, $base64Value) {
        $regex = '/(<|&lt;)ph id\s*=\s*["\']mtc_[0-9]+["\'] equiv-text\s*=\s*["\']base64:'.$base64Value.'["\']\s*\/(>|&gt;)/';

        return self::getResults($regex, $string);
    }

    /**
     * @param $string
     *
     * @return array
     */
    public static function extractPercentIge($string) {
        $regex = '/(<|&lt;)ph id\s*=\s*["\']mtc_[0-9]+["\'] equiv-text\s*=\s*["\']base64:JWk=["\']\s*\/(>|&gt;)ge/';

        return self::getResults($regex, $string);
    }

    /**
     * @param $regex
     * @param $string
     *
     * @return array
     */
    private static function getResults($regex, $string) {
        preg_match_all( $regex, $string, $phMap, PREG_SET_ORDER );

        return isset($phMap[0]) ? $phMap : [];
    }
}