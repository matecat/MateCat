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
use \Exception ;
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

    /**
     * @param $token json_encoded string
     * @return \Google_Service_Drive
     */
    public static function getService ( $token ) {

        if ( is_array( $token ) ) {
            $token = json_encode( $token ) ;
        }

        $oauthClient = GDrive::getClient() ;
        $oauthClient->setAccessToken( $token );
        $oauthClient->setAccessType( "offline" );
        $gdriveService = new \Google_Service_Drive( $oauthClient );


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

    public function copyFile ( $originFileId, $copyTitle ) {
        $copiedFile = new \Google_Service_Drive_DriveFile()  ;
        $copiedFile->setTitle( $copyTitle );

        try {
            return $this->gdriveService->files->copy( $originFileId, $copiedFile );
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