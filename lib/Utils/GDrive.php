<?php

class GDrive {

    const SESSION_ACTUAL_SOURCE_LANG = 'actualSourceLang';
    const SESSION_FILE_LIST = 'gdriveFileList';
    const SESSION_FILE_NAME = 'fileName';
    const SESSION_FILE_HASH = 'fileHash';

    const COOKIE_SOURCE_LANG = 'sourceLang';
    const COOKIE_TARGET_LANG = 'targetLang';

    const EMPTY_VAL = '_EMPTY_';

    const MIME_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    const MIME_PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    
    const MIME_GOOGLE_DOCS = 'application/vnd.google-apps.document';
    const MIME_GOOGLE_SLIDES = 'application/vnd.google-apps.presentation';
    const MIME_GOOGLE_SHEETS = 'application/vnd.google-apps.spreadsheet';
    
    public static function officeMimeFromGoogle ( $googleMime ) {
        switch( $googleMime ) {
            case self::MIME_GOOGLE_DOCS:
                return self::MIME_DOCX;

            case self::MIME_GOOGLE_SLIDES:
                return self::MIME_PPTX;

            case self::MIME_GOOGLE_SHEETS:
                return self::MIME_XLSX;
        }

        return $googleMime;
    }
    
    public static function officeExtensionFromMime ( $googleMime ) {
        switch( $googleMime ) {
            case self::MIME_GOOGLE_DOCS:
            case self::MIME_DOCX:
                return '.docx';

            case self::MIME_GOOGLE_SLIDES:
            case self::MIME_PPTX:
                return '.pptx';

            case self::MIME_GOOGLE_SHEETS:
            case self::MIME_XLSX:
                return '.xlsx';
        }
        
        return null;
    }

    public static function findFileIdByName ( $fileName, $session ) {
        if( isset( $session[ self::SESSION_FILE_LIST ] )
                && is_array( $session[ self::SESSION_FILE_LIST ] )
                && count( $session[ self::SESSION_FILE_LIST ] ) > 0 ) {

            $fileList = $session[ self::SESSION_FILE_LIST ];

            foreach ( $fileList as $fileId => $file ) {
                if( $file[ self::SESSION_FILE_NAME ] === $fileName ) {
                    return $fileId;
                }
            }
        }

        return null;
    }
}

