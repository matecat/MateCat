<?php

namespace Filters;
use finfo;
use INIT;
use Langs_Languages;
use MimeTypes\MimeTypes;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 27/05/16
 * Time: 12:08
 */

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
     * @var string
     */
    protected $source_lang;

    /**
     * OCRCheck constructor.
     *
     * @param $source_lang
     */
    public function __construct( $source_lang ) {
        $this->source_lang = $source_lang;
    }

    /**
     * @param $filePath
     *
     * @return bool
     */
    public function thereIsWarning( $filePath ){

        if( !INIT::$FILTERS_OCR_CHECK ){
            return false;
        }

        $languages = Langs_Languages::getInstance();

        if( array_search( $this->source_lang, $languages::getLanguagesWithOcrSupported() ) === false ){

            $mimeType = (new MimeTypes())->guessMimeType($filePath);
            if( array_search( $mimeType, $this->mimeTypes ) !== false  ){
                return true;
            }
        }

        return false;

    }

    /**
     * @param $filePath
     *
     * @return bool
     */
    public function thereIsError( $filePath ){

        if( !INIT::$FILTERS_OCR_CHECK ){
            return false;
        }

        $languages = Langs_Languages::getInstance();

        if( array_search( $this->source_lang, $languages::getLanguagesWithOcrNotSupported() ) !== false ){

            $mimeType = (new MimeTypes())->guessMimeType($filePath);
            if( array_search( $mimeType, $this->mimeTypes ) !== false  ){
                return true;
            }
        }

        return false;
    }

}