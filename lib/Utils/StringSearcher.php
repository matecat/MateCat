<?php

class StringSearcher {
    /**
     * @param string $haystack
     * @param string $needle
     * @param bool   $skipHtmlEntities
     * @param bool   $exactMatch
     * @param bool   $caseSensitive
     *
     * @return array
     */
    public static function search( $haystack, $needle, $skipHtmlEntities = true, $exactMatch = false, $caseSensitive = false ) {
        $pattern = self::getSearchPattern( $needle, $skipHtmlEntities, $exactMatch, $caseSensitive );

        if ( $skipHtmlEntities ) {
            $haystack = html_entity_decode( $haystack, ENT_COMPAT, 'UTF-8' );
        }

        preg_match_all( $pattern, $haystack, $matches, PREG_OFFSET_CAPTURE );

        return $matches[ 0 ];
    }

    /**
     * @param string $needle
     * @param bool   $skipHtmlEntities
     * @param bool   $exactMatch
     * @param bool   $caseSensitive
     *
     * @return string
     */
    private static function getSearchPattern( $needle, $skipHtmlEntities = true, $exactMatch = false, $caseSensitive = false ) {
        $pattern = '/';

        if ( $exactMatch ) {
            $pattern .= '\b';
        }

        $pattern .= $needle;

        if ( $exactMatch ) {
            $pattern .= '\b';
        }

        $pattern .= '/';

        if ( false === $caseSensitive ) {
            $pattern .= 'i';
        }

        if ( $skipHtmlEntities ) {
            $pattern .= 'u';
        }

        return $pattern;
    }
}
