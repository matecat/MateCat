<?php

namespace LQA;

use CJKLangUtils;

class SizeRestriction {

    /**
     * @var string
     */
    private $cleanedString;

    /**
     * @var int
     */
    private $limit;

    /**
     * SizeRestriction constructor.
     *
     * @param $string
     * @param $limit
     */
    public function __construct( $string, $limit ) {

        $string = $this->clearStringFromTags( $string );
        $string = $this->removeHiddenCharacters( $string );

        $this->cleanedString = $string;
        $this->limit         = $limit;
    }

    /**
     * @param string $string
     *
     * @return string
     */
    private function clearStringFromTags( $string ) {
        $cleanedText = preg_replace( '/&lt;ph(?:(?:(?!id).)*?)id="(?:[^"].*?)"(?:(?:(?!equiv-text).)*?)equiv-text="base64:((?:(?!&gt;).)*?)"\/&gt;/iu', '', $string );
        $cleanedText = preg_replace( '/&lt;g .*?id="(.*?)".*?&gt;/iu', '', $cleanedText );
        $cleanedText = preg_replace( '/&lt;(\/g)&gt;/iu', '', $cleanedText );
        $cleanedText = preg_replace( '/&lt;bx .*?id="(.*?)".*?\/&gt;/iu', '', $cleanedText );
        $cleanedText = preg_replace( '/&lt;ex .*?id="(.*?)".*?\/&gt;/iu', '', $cleanedText );
        $cleanedText = preg_replace( '/&lt;x .*?id="(.*?)".*?&gt;/iu', '', $cleanedText );
        $cleanedText = preg_replace( '/##\$(_A0)\$##/iu', ' ', $cleanedText );
        $cleanedText = preg_replace( '/##\$(_09)\$##/iu', ' ', $cleanedText );
        $cleanedText = preg_replace( '/##\$(_0D)\$##/iu', ' ', $cleanedText );
        $cleanedText = preg_replace( '/##\$(_0A)\$##/iu', ' ', $cleanedText );
        $cleanedText = preg_replace( '/##\$_(SPLIT)\$##/iu', ' ', $cleanedText );
        $cleanedText = preg_replace( '/&nbsp;/iu', ' ', $cleanedText );
        $cleanedText = html_entity_decode( $cleanedText, ENT_QUOTES | ENT_HTML5 );

        return $cleanedText;
    }

    /**
     * Remove every hidden character like word joiner or half spaces
     *
     * @param $string
     * @return string
     */
    private function removeHiddenCharacters($string)
    {
        $cleanedText = str_replace("&#8203;", "", $string);
        $cleanedText = str_replace("\xE2\x80\x8C", "", $cleanedText);
        $cleanedText = str_replace("\xE2\x80\x8B", "", $cleanedText);
        $cleanedText = str_replace("⁠", "", $cleanedText);

        return $cleanedText;
    }

    /**
     * @return bool
     */
    public function checkLimit() {
        return $this->getCleanedStringLength() <= $this->limit;
    }

    /**
     * @return int
     */
    public function getCharactersRemaining() {
        return $this->limit - $this->getCleanedStringLength();
    }

    /**
     * @return int
     */
    private function getCleanedStringLength() {

        $wordsArray = mb_str_split($this->cleanedString);
        $stringLength = 0;

        foreach ($wordsArray as $word){
            if(CJKLangUtils::isCjk($word)){
                $stringLength = $stringLength + 2;
            } else {
                $stringLength++;
            }
        }

        return $stringLength;
    }
}
