<?php

namespace Model\Conversion;

use INIT;
use Model\Conversion\MimeTypes\MimeTypes;
use Utils\Langs\Languages;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 27/05/16
 * Time: 12:08
 */

/**
 * Class OCRCheck
 *
 * This class is meant to control the mime type for files with specific extensions (image files)
 * The filters do not perform well with OCR in NON latin languages
 *
 * This class should return a warning
 *
 */
class OCRCheck {

    /**
     * @see https://www.iana.org/assignments/media-types/media-types.xhtml#image
     * @var array
     */
    private array $mimeTypes = [
            'image/',
            'application/octet-stream', //bmp or binary files
            'application/pdf',
    ];

    /**
     * @var string
     */
    protected string $source_lang;

    /**
     * OCRCheck constructor.
     *
     * @param string $source_lang
     */
    public function __construct( string $source_lang ) {
        $this->source_lang = $source_lang;
    }

    /**
     * @param string $filePath
     *
     * @return bool
     */
    public function thereIsWarning( string $filePath ): bool {

        if ( !INIT::$FILTERS_OCR_CHECK ) {
            return false;
        }

        $languages = Languages::getInstance();

        if ( !in_array( $this->source_lang, $languages::getLanguagesWithOcrSupported() ) ) {

            $mimeType = ( new MimeTypes() )->guessMimeType( $filePath );
            foreach ( $this->mimeTypes as $mType ) {
                if ( stripos( $mimeType, $mType ) !== false ) {
                    return true;
                }
            }
        }

        return false;

    }

    /**
     * @param string $filePath
     *
     * @return bool
     */
    public function thereIsError( string $filePath ): bool {

        if ( !INIT::$FILTERS_OCR_CHECK ) {
            return false;
        }

        $languages = Languages::getInstance();

        if ( in_array( $this->source_lang, $languages::getLanguagesWithOcrNotSupported() ) ) {

            $mimeType = ( new MimeTypes() )->guessMimeType( $filePath );
            foreach ( $this->mimeTypes as $mType ) {
                if ( stripos( $mimeType, $mType ) !== false ) {
                    return true;
                }
            }
        }

        return false;
    }

}