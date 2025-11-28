<?php

namespace Utils\LQA\SizeRestriction;
class CJKLangUtils {
    const string CHINESE_REGEX  = "/\p{Han}+/u";
    const string JAPANESE_REGEX = '/[\x{3041}-\x{3096}\x{30A0}-\x{30FF}\x{3400}-\x{4DB5}\x{4E00}-\x{9FCB}\x{F900}-\x{FA6A}\x{2E80}-\x{2FD5}\x{FF5F}-\x{FF9F}\x{3000}-\x{303F}\x{31F0}-\x{31FF}\x{3220}-\x{3243}\x{3280}-\x{337F}\x{FF01}-\x{FF5E}]/u';
    const string KOREAN_REGEX   = '/[\x{3130}-\x{318F}\x{AC00}-\x{D7AF}]/u';

    /**
     * @param $string
     *
     * @return int
     */
    public static function getMatches( $string ): int {
        $count = 0;

        // The chinese regular expression is a subset of the japanese one.
        // So, we rely only on the japanese regular expression to get the match count
        preg_match_all( self::JAPANESE_REGEX, $string, $japaneseMatches );
        preg_match_all( self::KOREAN_REGEX, $string, $korenMatches );

        foreach ( $japaneseMatches[ 0 ] as $japaneseMatch ) {
            $count = $count + ( strlen( $japaneseMatch ) - 1 );
        }

        foreach ( $korenMatches[ 0 ] as $korenMatch ) {
            $count = $count + ( strlen( $korenMatch ) - 1 );
        }

        return $count;
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    public static function isCjk( string $string ): bool {
        return self::isChinese( $string ) or self::isJapanese( $string ) or self::isKorean( $string );
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    public static function isChinese( string $string ): bool {
        return self::check( self::CHINESE_REGEX, $string );
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    public static function isJapanese( string $string ): bool {
        return self::check( self::JAPANESE_REGEX, $string );
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    public static function isKorean( string $string ): bool {
        return self::check( self::KOREAN_REGEX, $string );
    }

    /**
     * @param string $regex
     * @param string $string
     *
     * @return bool
     */
    private static function check( string $regex, string $string ): bool {
        preg_match( $regex, $string, $matches );

        return !empty( $matches );
    }
}