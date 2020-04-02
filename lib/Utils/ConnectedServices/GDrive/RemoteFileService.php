<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/11/2016
 * Time: 15:28
 */

namespace ConnectedServices\GDrive;

use ConnectedServices\AbstractRemoteFileService;
use Exception;
use Log;

class RemoteFileService extends AbstractRemoteFileService {

    const MIME_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    const MIME_PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    const MIME_GOOGLE_DOCS   = 'application/vnd.google-apps.document';
    const MIME_GOOGLE_SLIDES = 'application/vnd.google-apps.presentation';
    const MIME_GOOGLE_SHEETS = 'application/vnd.google-apps.spreadsheet';


    protected $raw_token;
    protected $gdriveService;

    /**
     * RemoteFileService constructor.
     *
     * @param $raw_token
     *
     * @throws Exception
     */
    public function __construct( $raw_token ) {
        $this->raw_token     = $raw_token;
        $this->gdriveService = self::getService( $this->raw_token );
    }

    /**
     * @param string $token
     *
     * @return \Google_Service_Drive
     * @throws Exception
     */
    public static function getService( $token ) {

        if ( is_array( $token ) ) {
            $token = json_encode( $token );
        }

        $oauthClient = GoogleClientFactory::create();
        $oauthClient->setAccessToken( $token );

        return new \Google_Service_Drive( $oauthClient );
    }

    /**
     * @param $remoteFile
     * @param $content
     *
     * @return \Google_Service_Drive_DriveFile
     */
    public function updateFile( $remoteFile, $content ) {

        $optParams = [
                'fields' => 'capabilities, webViewLink',
        ];

        try {
            $gdriveFile = $this->gdriveService->files->get( $remoteFile->remote_id, $optParams );
            $capabilities = $gdriveFile->getCapabilities();
            $parents      = $gdriveFile->getParents();

            $this->updateFileOnGDrive( $remoteFile->remote_id, $gdriveFile, $content, $capabilities->canAddMyDriveParent, $parents );

            return $gdriveFile;
        } catch ( Exception $e ) {
            // Exception Caught, check if the token is expired:
            $this->__checkTokenExpired();

            Log::doJsonLog( 'Failed to access file from Google Drive: ' . $e->getMessage() );
        }
    }

    /**
     * @param $remote_id
     *
     * @return \Google_Service_Drive_DriveFile
     */
    public function getFileLink( $remote_id ) {

        $optParams = [
                'fields' => 'capabilities, webViewLink',
        ];

        return $this->gdriveService->files->get( $remote_id, $optParams );
    }

    /**
     * __checkTokenExpired
     */
    private function __checkTokenExpired() {
        if ( $this->gdriveService->getClient()->isAccessTokenExpired() ) {
            // TODO: handle the case in which someone is asking for access to the file
            // but we are unable to refresh the token.
            $this->gdriveService->getClient()->createAuthUrl();
        }
    }

    /**
     * @param string                          $remoteId
     * @param \Google_Service_Drive_DriveFile $gdriveFile
     * @param string                          $content
     * @param bool                            $canAddMyDriveParent
     * @param array                           $parents
     */
    private function updateFileOnGDrive( $remoteId, \Google_Service_Drive_DriveFile $gdriveFile, $content, $canAddMyDriveParent, $parents ) {

        $newGDriveFileInstance = new \Google_Service_Drive_DriveFile();
        $newGDriveFileInstance->setDriveId( $remoteId );
        $newGDriveFileInstance->setMimeType( self::officeMimeFromGoogle( $gdriveFile->mimeType ) );
        $newGDriveFileInstance->setName( $gdriveFile->getName() );
        $newGDriveFileInstance->setDescription( $gdriveFile->getDescription() );
        $newGDriveFileInstance->setKind( $gdriveFile->getKind() );

        $optParams = [
                'mimeType'            => $gdriveFile->mimeType,
                'data'                => $content,
                'uploadType'          => 'media',
        ];

        // According to:
        // https://developers.google.com/drive/api/v3/multi-parenting
        // call update() with the addParents field set to the new parent folder's ID
        // and the enforceSingleParent set to true, to add a parent folder for the file.
        if(true === $canAddMyDriveParent and false === empty($parents)){
            $optParams['enforceSingleParent'] = true;
            $optParams['addParents'] = $parents[0]; // the ID of the first parent
        }

        $this->gdriveService->files->update( $remoteId, $newGDriveFileInstance, $optParams );
    }

    /**
     * @param $originFileId
     * @param $copyTitle
     *
     * @return \Google_Service_Drive_DriveFile|null
     */
    public function copyFile( $originFileId, $copyTitle ) {
        $copiedFile = new \Google_Service_Drive_DriveFile();
        $copiedFile->setName( $copyTitle );

        // According to:
        // https://developers.google.com/drive/api/v3/multi-parenting
        // call copy() with 'enforceSingleParent' field set to true to create a file in a single parent
        $optParams = [
            'enforceSingleParent' => true,
        ];

        try {
            return $this->gdriveService->files->copy( $originFileId, $copiedFile, $optParams );
        } catch ( Exception $e ) {
            print "An error occurred: " . $e->getMessage();
        }

        return null;
    }

    /**
     * @param $googleMime
     *
     * @return string
     */
    public static function officeMimeFromGoogle( $googleMime ) {
        switch ( $googleMime ) {
            case self::MIME_GOOGLE_DOCS:
                return self::MIME_DOCX;

            case self::MIME_GOOGLE_SLIDES:
                return self::MIME_PPTX;

            case self::MIME_GOOGLE_SHEETS:
                return self::MIME_XLSX;
        }

        return $googleMime;
    }

    /**
     * @param $googleMime
     *
     * @return string|null
     */
    public static function officeExtensionFromMime( $googleMime ) {
        switch ( $googleMime ) {
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