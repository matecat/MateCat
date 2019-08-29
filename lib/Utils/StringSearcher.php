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
        $pattern .= self::escapeNeedle( $needle, $exactMatch );
        $pattern .= '/';

        if ( false === $caseSensitive ) {
            $pattern .= 'i';
        }

        if ( $skipHtmlEntities ) {
            $pattern .= 'u';
        }

        return $pattern;
    }

    /**
     * @param string $needle
     * @param bool   $exactMatch
     *
     * @return string
     */
    public static function escapeNeedle( $needle, $exactMatch = true ) {

        $mustBeEscaped            = [ '.', '+', '*', '?', '[', '^', ']', '$', '(', ')', '{', '}', '=', '!', '<', '>', '|', '€', '#' ];
        $mustBeEscapedReplacement = [
                '\\.\\', '\\+\\', '\\*\\', '\\?\\', '\\[\\', '\\^\\', '\\]\\', '\\$\\', '\\(\\', '\\)\\', '\\{\\', '\\}\\', '\\=\\', '\\!\\', '\\<\\', '\\>\\', '\\|\\', '\\€\\', '\\#\\'
        ];

        $needle  = str_replace( $mustBeEscaped, $mustBeEscapedReplacement, $needle );
        $results = preg_split( "/\\\\/", $needle );

        $final = '';

        // the original string now is splitted by double \ surrounding the characters which must to be escaped.
        //
        // Example:
        // Hi, I have got 300$ -----> Hi, I have got 300\$\
        //
        // after preg_split it becomes an array:
        // Array => {
        //    [0] => "Hi, I have got 300",
        //    [1] => "$",
        // }
        foreach ( $results as $result ) {

            if ( $result !== "" ) {
                // check if the string is a character to be escaped
                if ( in_array( $result, $mustBeEscaped ) ) {
                    $final .= '\\' . $result;
                } else {

                    // calculate the white spaces at the beginning and at the end of the string and preserve them
                    $leftSpaces  = strlen( $result ) - strlen( ltrim( $result ) );
                    $rightSpaces = strlen( $result ) - strlen( rtrim( $result ) );

                    for ( $i = 0; $i < $leftSpaces; $i++ ) {
                        $final .= ' ';
                    }

                    // add \b if $exactMatch is enabled
                    if ( $exactMatch ) {
                        $final .= '\\b';
                    }

                    $final .= trim( $result );

                    if ( $exactMatch ) {
                        $final .= '\\b';
                    }

                    for ( $i = 0; $i < $rightSpaces; $i++ ) {
                        $final .= ' ';
                    }
                }
            }
        }

        return $final;
    }
}
