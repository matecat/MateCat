<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/11/2016
 * Time: 15:28
 */

namespace ConnectedServices\GDrive;


use ConnectedServices\AbstractRemoteFileService;

use ConnectedServices\GDrive;
use Google_Service_Drive_DriveFile ;

use \Exception ;
use OauthTokenEncryption ;
use Google_Service_Drive;

use Google_Service_Drive_Permission ;

use Files_FileDao ;

use RemoteFiles_RemoteFileDao ;

use Jobs_JobDao ;

use Log  ;

class RemoteFileService extends AbstractRemoteFileService
{

    const MIME_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    const MIME_PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    const MIME_GOOGLE_DOCS = 'application/vnd.google-apps.document';
    const MIME_GOOGLE_SLIDES = 'application/vnd.google-apps.presentation';
    const MIME_GOOGLE_SHEETS = 'application/vnd.google-apps.spreadsheet';


    protected $raw_token ;
    protected $gdriveService ;

    public function __construct( $raw_token ) {
        $this->raw_token = $raw_token ;

        $this->gdriveService = self::getService( $this->raw_token ) ;
    }

    public static function getService ( $token ) {
        $oauthClient = GDrive::getClient() ;
        $oauthClient->setAccessToken( $token );
        $oauthClient->setAccessType( "offline" );
        $gdriveService = new Google_Service_Drive( $oauthClient );

        return $gdriveService;
    }

    public function updateFile( $remoteFile, $content ) {

        try {
            $gdriveFile = $this->gdriveService->files->get( $remoteFile->remote_id );
            $this->updateFileOnGDrive( $remoteFile->remote_id, $gdriveFile, $content ) ;

            return $gdriveFile ;
        } catch ( Exception $e ) {
            // Exception Caught, check if the token is expired:
            $this->__checkTokenExpired();

            Log::doLog( 'Failed to access file from Google Drive: ' . $e->getMessage() );
        }
    }

    /**
     * __checkTokenExpired
     */
    private function __checkTokenExpired() {
        if ( $this->gdriveService->getClient()->isAccessTokenExpired() ) {
            // TODO: handle the case in which someone is asking for access to the file
            // but we are unable to refresh the token.
            $authUrl = $this->gdriveService->getClient()->createAuthUrl();



        }
    }

    private function updateFileOnGDrive( $remoteId, $gdriveFile, $content ) {
        $mimeType =  GDrive\RemoteFileService::officeMimeFromGoogle( $gdriveFile->mimeType );

        $gdriveFile->setMimeType( $mimeType );

        $additionalParams = array(
            'mimeType' => $mimeType,
            'data' => $content,
            'uploadType' => 'media',
            'newRevision' => FALSE
        );

        $upload = $this->gdriveService->files->update( $remoteId, $gdriveFile, $additionalParams );
    }


    /**
     * Method to insert a new permission in a Google Drive file to grant anyone to access it by
     * its URL.
     *
     * @param   \Array                           $session
     * @param   Google_Service_Drive            $service
     * @param   String                          $fileId
     * @return  \Google_Service_Drive_Permission
     */
    public static function grantFileAccessByUrl ( $session, $service, $fileId ) {
        $dao = new \Users_UserDao( \Database::obtain() );
        $user = $dao->getByUid( $session[ 'uid' ] );

        if($user != null) {
            $urlPermission = new Google_Service_Drive_Permission();
            $urlPermission->setValue( $user->email );
            $urlPermission->setType( 'anyone' );
            $urlPermission->setRole( 'reader' );
            $urlPermission->setWithLink( true );

            try {
                return $service->permissions->insert( $fileId, $urlPermission );
            } catch (Exception $e) {
                print "An error occurred: " . $e->getMessage();
            }
        }

        return null;
    }


    public static function insertRemoteFile ( $id_file, $id_job, $service, $session ) {
        $file = Files_FileDao::getById( $id_file );
        $listRemoteFiles = RemoteFiles_RemoteFileDao::getByFileId( $id_file, 1 );
        $remoteFile = $listRemoteFiles[0];

        $job = Jobs_JobDao::getById( $id_job );

        $gdriveFile = $service->files->get( $remoteFile->remote_id );

        $fileTitle = $gdriveFile->getTitle();

        $translatedFileTitle = $fileTitle . ' - ' . $job->target;

        $copiedFile = self::copyFile( $service, $remoteFile->remote_id, $translatedFileTitle );

        RemoteFiles_RemoteFileDao::insert( $id_file, $id_job, $copiedFile->id );

        self::grantFileAccessByUrl( $session, $service, $copiedFile->id );
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



}