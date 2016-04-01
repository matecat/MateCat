<?php

use OauthClient ;
use Google_Service_Drive ;
use Google_Service_Drive_DriveFile ;
use RemoteFiles_RemoteFileDao ;

class GDrive {

    const SESSION_FILE_LIST = 'gdriveFileList';
    const SESSION_FILE_NAME = 'fileName';
    const SESSION_FILE_HASH = 'fileHash';

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

    public static function sessionHasFiles ( $session ) {
        if( isset( $session[ self::SESSION_FILE_LIST ] )
                && !empty( $session[ self::SESSION_FILE_LIST ] ) ) {
            return true;
        }

        return false;
    }

    public static function findFileIdByName ( $fileName, $session ) {
        if( self::sessionHasFiles( $session ) ) {
            $fileList = $session[ self::SESSION_FILE_LIST ];

            foreach ( $fileList as $fileId => $file ) {
                if( $file[ self::SESSION_FILE_NAME ] === $fileName ) {
                    return $fileId;
                }
            }
        }

        return null;
    }

    public static function getService ( $session ) {
        if( isset( $session[ 'uid' ] ) && !empty( $session[ 'uid' ] ) ) {
            $dao = new \Users_UserDao( \Database::obtain() );
            $user = $dao->getByUid( $session[ 'uid' ] );
            $token = $user->oauth_access_token ;

            $oauthClient = OauthClient::getInstance()->getClient();
            $oauthClient->setAccessToken( $token );
            $gdriveService = new Google_Service_Drive( $oauthClient );

            return $gdriveService;
        }

        return null;
    }

    public static function copyFile ( $service, $originFileId, $copyTitle ) {
        $copiedFile = new Google_Service_Drive_DriveFile();
        $copiedFile->setTitle( $copyTitle );

        try {
            return $service->files->copy( $originFileId, $copiedFile );
        } catch (Exception $e) {
            print "An error occurred: " . $e->getMessage();
        }

        return null;
    }

    public static function insertRemoteFile ( $id_file, $id_job, $service ) {
        $file = Files_FileDao::getById( $id_file );
        $job = Jobs_JobDao::getById( $id_job );

        $fileTitle = substr( $file->filename, 0, -5 );

        $translatedFileTitle = $fileTitle . ' - ' . $job->target;

        $copiedFile = self::copyFile( $service, $file->remote_id, $translatedFileTitle );

        RemoteFiles_RemoteFileDao::insert( $id_file, $id_job, $copiedFile->id );
    }
}

