<?php

namespace Utils\LQA\SizeRestriction;

use Exception;
use Model\FeaturesBase\FeatureSet;

class SizeRestriction {

    /**
     * @var string
     */
    private string $cleanedString;

    /**
     * @var FeatureSet
     */
    private FeatureSet $featureSet;

    /**
     * SizeRestriction constructor.
     *
     * @param string     $string
     * @param FeatureSet $featureSet
     */
    public function __construct( string $string, FeatureSet $featureSet ) {

        $string = $this->clearStringFromTags( $string );
        $string = $this->removeHiddenCharacters( $string );

        $this->cleanedString = $string;
        $this->featureSet    = $featureSet;
    }

    /**
     * @param string $string
     *
     * @return string
     */
    private function clearStringFromTags( string $string ): string {
        $cleanedText = preg_replace( '#&lt;ph(?:(?!id).)*?id="[^"].*?"(?:(?!equiv-text).)*?equiv-text="base64:((?:(?!&gt;).)*?)"/&gt;#iu', '', $string );
        $cleanedText = preg_replace( '/&lt;g .*?id="(.*?)".*?&gt;/iu', '', $cleanedText );
        $cleanedText = preg_replace( '#&lt;(/g)&gt;#iu', '', $cleanedText );
        $cleanedText = preg_replace( '#&lt;bx .*?id="(.*?)".*?/&gt;#iu', '', $cleanedText );
        $cleanedText = preg_replace( '#&lt;ex .*?id="(.*?)".*?/&gt;#iu', '', $cleanedText );
        $cleanedText = preg_replace( '/&lt;x .*?id="(.*?)".*?&gt;/iu', '', $cleanedText );
        $cleanedText = preg_replace( '/##\$(_A0)\$##/iu', ' ', $cleanedText );
        $cleanedText = preg_replace( '/##\$(_09)\$##/iu', ' ', $cleanedText );
        $cleanedText = preg_replace( '/##\$(_0D)\$##/iu', ' ', $cleanedText );
        $cleanedText = preg_replace( '/##\$(_0A)\$##/iu', ' ', $cleanedText );
        $cleanedText = preg_replace( '/##\$_(SPLIT)\$##/iu', ' ', $cleanedText );
        $cleanedText = preg_replace( '/&nbsp;/iu', ' ', $cleanedText );

        return html_entity_decode( $cleanedText, ENT_QUOTES | ENT_HTML5 );
    }

    /**
     * Remove every hidden character like word joiner or half-spaces
     *
     * @param string $string
     *
     * @return string
     */
    private function removeHiddenCharacters( string $string ): string {
        $cleanedText = str_replace( "&#8203;", "", $string );
        $cleanedText = str_replace( "\xE2\x80\x8C", "", $cleanedText );
        $cleanedText = str_replace( "\xE2\x80\x8B", "", $cleanedText );

        return str_replace( "⁠", "", $cleanedText );
    }

    /**
     * @param $limit
     *
     * @return bool
     */
    public function checkLimit( $limit ): bool {
        return $this->getCleanedStringLength() <= $limit;
    }

    /**
     * @param $limit
     *
     * @return int
     */
    public function getCharactersRemaining( $limit ): int {
        return $limit - $this->getCleanedStringLength();
    }

    /**
     * This method is responsible for counting the characters in a string according to these rules:
     *
     * CJK characters should be counted as raw UTF-8 bytes.
     * The rest should be counted as UTF-8 characters.
     *
     * @return int
     */
    public function getCleanedStringLength(): int {

        try {

            $featureCounts = $this->featureSet->filter( 'characterLengthCount', $this->cleanedString );

            if ( is_array( $featureCounts ) ) {
                return array_sum( $featureCounts );
            }

            return array_sum( [
                    "baseLength"   => mb_strlen( $this->cleanedString ),
                    "cjkMatches"   => CJKLangUtils::getMatches( $this->cleanedString ),
                    "emojiMatches" => EmojiUtils::getMatches( $this->cleanedString )
            ] );

        } catch ( Exception $e ) {
        }

        return 0;

    }
}
