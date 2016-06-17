<?php

namespace Filters;
use finfo,
        INIT,
        Langs_Languages;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 27/05/16
 * Time: 12:08
 */

//TODO remove when Filters will return a warning for the ocr with a wrong language type
/**
 * Class OCRCheck
 *
 * This class is meant to control the mime type for files with specific extensions ( image files )
 * The filters does not perform well with OCR in NON latin languages
 *
 * This class should return a warning
 *
 */
class OCRCheck {

    /**
     * @var array
     */
    private $mimeTypes = array(
            'image/jpeg',
            'image/gif',
            'application/octet-stream', //bmp files
            'image/tiff',
            'application/pdf',
            'image/jpeg',
    );

    /**
     * @var array
     */
    private $supportedLatinLangs = array(
            "Afrikaans",
            "Albanian",
            "Aragonese",
            "Asturian",
            "Basque",
            "Belarus",
            "Bosnian",
            "Breton",
            "Catalan",
            "Cebuano",
            "Croatian",
            "Czech",
            "Danish",
            "Dutch",
            "English",
            "English US",
            "Esperanto",
            "Estonian",
            "Faroese",
            "Finnish",
            "French",
            "Galician",
            "German",
            "Hawaiian",
            "Hungarian",
            "Icelandic",
            "Indonesian",
            "Irish Gaelic",
            "Italian",
            "Latvian",
            "Lithuanian",
            "Maori",
            "Malay",
            "Maltese",
            "Montenegrin",
            "Ndebele",
            "Norwegian Bokmål",
            "Norwegian Nynorsk",
            "Occitan",
            "Polish",
            "Portuguese",
            "Quechua",
            "Romanian",
            "Serbian Latin",
            "Slovak",
            "Slovenian",
            "Spanish",
            "Swahili",
            "Swedish",
            "Tagalog",
            "Tatar",
            "Tsonga",
            "Turkish",
            "Turkmen",
            "Uzbek",
            "Venda",
            "Vietnamese",
            "Welsh",
            "Xhosa",
            "Zulu"
    );

    /**
     * @var string
     */
    protected $localizedLangName;

    /**
     * OCRCheck constructor.
     *
     * @param $source_lang
     */
    public function __construct( $source_lang ) {
        $this->localizedLangName = Langs_Languages::getInstance()->getLocalizedName( $source_lang );
    }

    /**
     * @param $filePath
     *
     * @return bool
     */
    public function isValid( $filePath ){

        if( !INIT::$FILTERS_OCR_CHECK ){
            return true;
        }

        if( array_search( $this->localizedLangName, $this->supportedLatinLangs ) === false ){
            /**
             * @var $finfo finfo
             */
            $finfo = new finfo();
            $mimeType = $finfo->file( $filePath, FILEINFO_MIME_TYPE );
            if( array_search( $mimeType, $this->mimeTypes ) !== false  ){
                return false;
            }
        }

        return true;

    }

}