<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/11/2016
 * Time: 15:28
 */

namespace ConnectedServices\Google\GDrive;

use ArrayObject;
use CatUtils;
use ConnectedServices\ConnectedServiceDao;
use ConnectedServices\ConnectedServiceStruct;
use Constants;
use DirectoryIterator;
use Exception;
use FeatureSet;
use FilesConverter;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use FilesStorage\S3FilesStorage;
use FilesystemIterator;
use Filters\FiltersConfigTemplateStruct;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_Permission;
use GuzzleHttp\Psr7\Response;
use INIT;
use Jobs_JobDao;
use Log;
use Predis\Connection\ConnectionException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionException;
use RemoteFiles_RemoteFileDao;
use RuntimeException;
use Users_UserStruct;
use Utils;

/**
 * Class Session
 * @package ConnectedServices\GDrive
 */
class Session {

    const FILE_LIST             = 'gdriveFileList';
    const FILE_NAME             = 'fileName';
    const FILE_HASH             = 'fileHash';
    const CONNNECTED_SERVICE_ID = 'connectedServiceId';

    protected string                       $guid;
    protected string                       $source_lang;
    protected string                       $target_lang;
    protected ?string                      $seg_rule                      = null;
    protected array                        $session                       = [];
    protected ?FiltersConfigTemplateStruct $filters_extraction_parameters = null;
    protected ?Google_Service_Drive        $service                       = null;
    protected ?array                       $token                         = null;

    /**
     * @var ?ConnectedServiceStruct
     */
    protected ?ConnectedServiceStruct $serviceStruct = null;

    /**
     * @var AbstractFilesStorage
     */
    protected AbstractFilesStorage $files_storage;

    /**
     * @var FeatureSet
     */
    protected FeatureSet $featureSet;

    /**
     * MUST NOT TO BE CALLED FROM THE cli
     *
     * Session constructor.
     * @throws Exception
     */
    public function __construct() {
        if ( !isset( $_SESSION[ 'uid' ] ) ) {
            return;
        }

        $this->session = &$_SESSION;

        $this->files_storage = FilesStorageFactory::create();
    }

    /**
     * @return array
     */
    public function getSession(): array {
        return $this->session;
    }

    /**
     * Creates a new instance of the Session class for CLI usage.
     *
     * @param $session
     *
     * @return Session
     * @throws Exception
     */
    public static function getInstanceForCLI( $session ): Session {
        if ( PHP_SAPI != 'cli' ) {
            throw new RuntimeException( "This method MUST be called by CLI." );
        }
        $_SESSION =& $session;

        return new self();
    }

    /**
     * @param string                           $newSourceLang
     * @param string|null                      $newSegmentationRule
     * @param FiltersConfigTemplateStruct|null $filtersExtractionParameters
     *
     * @return bool
     */
    public function reConvert( string $newSourceLang, ?string $newSegmentationRule = null, ?FiltersConfigTemplateStruct $filtersExtractionParameters = null ): bool {

        $this->setConversionParams( $this->session[ "upload_token" ], $newSourceLang, 'en-US', $newSegmentationRule, $filtersExtractionParameters );

        $fileList = $this->session[ self::FILE_LIST ];

        foreach ( $fileList as $fileId => $file ) {

            try {

                $generatedSha = $this->doConversion( $file[ self::FILE_NAME ] );

                if ( empty( $generatedSha ) ) {
                    throw new Exception( 'Error when converting file.' );
                }

                $this->session[ self::FILE_LIST ][ $fileId ][ self::FILE_HASH ] = $generatedSha;

            } catch ( Exception $e ) {
                return false;
            }

        }

        return true;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getFileStructureForJsonOutput(): array {
        $response = [];

        if ( empty( $this->session[ self::FILE_LIST ] ) ) {
            return $response;
        }

        foreach ( $this->session[ self::FILE_LIST ] as $fileId => $file ) {

            $fileName = $file[ self::FILE_NAME ];

            if ( AbstractFilesStorage::isOnS3() ) {
                $path     = $this->getGDriveFilePathForS3( $file );
                $s3Client = S3FilesStorage::getStaticS3Client();
                $s3       = $s3Client->getItem( [
                                'bucket' => S3FilesStorage::getFilesStorageBucket(),
                                'key'    => $path
                        ]
                );

                $response[ 'files' ][] = [
                        'fileId'        => $fileId,
                        'fileName'      => $fileName,
                        'fileSize'      => $s3[ 'ContentLength' ],
                        'fileExtension' => INIT::$MIME_TYPES[ $s3[ 'ContentType' ] ][ 0 ]
                ];

            } else {
                $path = $this->getGDriveFilePath( $file );
                if ( file_exists( $path ) !== false ) {
                    $fileSize = filesize( $path );

                    $fileExtension = pathinfo( $fileName, PATHINFO_EXTENSION );

                    $response[ 'files' ][] = [
                            'fileId'        => $fileId,
                            'fileName'      => $fileName,
                            'fileSize'      => $fileSize,
                            'fileExtension' => $fileExtension
                    ];
                } else {
                    unset( $this->session[ self::FILE_LIST ][ $fileId ] );
                }
            }
        }

        return $response;
    }

    /**
     * MUST NOT TO BE CALLED FROM THE cli
     */
    public function cleanupSessionFiles() {
        if ( $this->sessionHasFiles() ) {
            unset( $this->session[ self::FILE_LIST ] );
            unset( $_SESSION[ self::FILE_LIST ] );
        }
    }

    /**
     * @return ?array
     * @throws Exception
     */
    public function getToken(): ?array {
        if ( is_null( $this->token ) ) {
            if ( $this->session[ 'user' ] !== null ) {
                if ( $this->session[ 'user' ] instanceof ArrayObject ) { // comes from CLI (ProjectManager)
                    $this->session[ 'user' ] = new Users_UserStruct( $this->session[ 'user' ]->getArrayCopy() );
                }
                $this->token = $this->getTokenByUser( $this->session[ 'user' ] );
            }
        }

        return $this->token;
    }

    /**
     * @param Users_UserStruct $user
     *
     * @return array|null
     * @throws Exception
     */
    public function getTokenByUser( Users_UserStruct $user ): ?array {
        $serviceDao          = new ConnectedServiceDao();
        $this->serviceStruct = $serviceDao->findDefaultServiceByUserAndName( $user, 'gdrive' );

        if ( !$this->serviceStruct ) {
            return null;
        } else {
            return $this->serviceStruct->getDecodedOauthAccessToken();
        }
    }

    /**
     * Adds files to the session variables.
     *
     * @param string $fileId
     * @param string $fileName
     * @param array  $fileHash
     */
    public function addFiles( string $fileId, string $fileName, array $fileHash ) {

        if ( !isset( $this->session[ self::FILE_LIST ] )
                || !is_array( $this->session[ self::FILE_LIST ] ) ) {

            $this->session[ self::FILE_LIST ] = [];
        }

        $this->session[ self::FILE_LIST ][ $fileId ] = [
                self::FILE_NAME             => $fileName,
                self::FILE_HASH             => $fileHash,
                self::CONNNECTED_SERVICE_ID => $this->serviceStruct->id,
        ];
    }

    /**
     * @return bool
     */
    public function hasFiles(): bool {
        return ( isset( $this->session[ self::FILE_LIST ] ) and count( $this->session[ self::FILE_LIST ] ) > 0 );
    }

    /**
     * @return bool
     */
    public function sessionHasFiles(): bool {
        if ( isset( $this->session[ self::FILE_LIST ] )
                && !empty( $this->session[ self::FILE_LIST ] ) ) {
            return true;
        }

        return false;
    }

    /**
     * @param string $fileName
     *
     * @return int|string|null
     */
    public function findFileIdByName( string $fileName ): ?string {
        if ( $this->hasFiles() ) {
            foreach ( $this->session[ self::FILE_LIST ] as $singleFileId => $file ) {
                if ( $file[ self::FILE_NAME ] === $fileName ) {
                    return $singleFileId;
                }
            }
        }

        return null;
    }

    /**
     * Gets the service if token is available.
     * If the token is not found in the database, then returns FALSE ;
     *
     * Memoize the response.
     *
     * The Returned token may still be expired.
     *
     * @param Google_Client $gClient
     *
     * @return Google_Service_Drive|null
     * @throws Exception
     */
    public function getService( Google_Client $gClient ): ?Google_Service_Drive {
        if ( is_null( $this->service ) ) {

            $token = $this->getToken();

            if ( $token ) {
                $this->service = RemoteFileService::getService( $token, $gClient );
            } else {
                $this->service = null;
            }
        }

        return $this->service;
    }

    /**
     * @param Google_Client $gClient
     *
     * @return RemoteFileService
     * @throws Exception
     */
    public function buildRemoteFile( Google_Client $gClient ): RemoteFileService {
        if ( !$this->getToken() ) {
            throw  new Exception( 'Cannot build RemoteFile without a token' );
        }

        return new RemoteFileService( $this->token, $gClient );
    }

    public function clearFileListFromSession() {
        unset( $this->session[ self::FILE_LIST ] );
    }

    /**
     * @param string $fileId
     * @param string $source
     * @param string $segmentationRule
     * @param int $filtersTemplate
     *
     * @return bool
     * @throws ConnectionException
     * @throws ReflectionException
     */
    public function removeFile( string $fileId, $source, $segmentationRule = null, $filtersTemplate = 0 ): bool {
        $success = false;

        if ( isset( $this->session[ self::FILE_LIST ][ $fileId ] ) ) {
            $file      = $this->session[ self::FILE_LIST ][ $fileId ];
            $pathCache = $this->getCacheFileDir( $file );

            if ( S3FilesStorage::isOnS3() ) {
                $s3Client = S3FilesStorage::getStaticS3Client();
                $s3Client->deleteFolder( [
                                'bucket' => S3FilesStorage::getFilesStorageBucket(),
                                'key'    => $pathCache
                        ]
                );
            } else {
                $this->deleteDirectory( $pathCache );
            }

            $tempUploadedFileDir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $this->session[ 'upload_token' ];

            /** @var DirectoryIterator $item */
            foreach (
                    new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator( $tempUploadedFileDir, FilesystemIterator::SKIP_DOTS ),
                            RecursiveIteratorIterator::SELF_FIRST
                    ) as $item
            ) {
                $target   = explode( '__', $pathCache );
                $hashFile = $file[ 'fileHash' ] . "|" . end( $target );

                if ( $item->getFilename() === $file[ 'fileName' ] or $item->getFilename() === $hashFile ) {
                    CatUtils::deleteSha($tempUploadedFileDir."/". $file[ 'fileName' ], $source, $segmentationRule, $filtersTemplate);
                    unlink( $item );
                }
            }

            unset( $this->session[ self::FILE_LIST ] [ $fileId ] );

            Log::doJsonLog( 'File ' . $fileId . ' removed.' );

            $success = true;
        }

        return $success;
    }

    /**
     * @param $source
     * @param null $segmentationRule
     * @param int $filtersTemplate
     * @throws ConnectionException
     * @throws ReflectionException
     */
    public function removeAllFiles($source, $segmentationRule = null, $filtersTemplate = 0) {
        foreach ( $this->session[ self::FILE_LIST ] as $singleFileId => $file ) {
            $this->removeFile( $singleFileId, $source, $segmentationRule, $filtersTemplate );
        }

        unset( $this->session[ self::FILE_LIST ] );
    }

    /**
     * @param string $dir
     */
    private function deleteDirectory( string $dir ) {
        $it    = new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS );
        $files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );

        foreach ( $files as $file ) {
            if ( $file->isDir() ) {
                rmdir( $file->getRealPath() );
            } else {
                unlink( $file->getRealPath() );
            }
        }

        rmdir( $dir );
    }

    /**
     * @param array $file
     *
     * @return string
     */
    private function getCacheFileDir( array $file ): string {
        $sourceLang = $this->session[ Constants::SESSION_ACTUAL_SOURCE_LANG ];

        $fileHash = $file[ self::FILE_HASH ][ 'cacheHash' ];

        $fs          = $this->files_storage;
        $cacheTreeAr = $fs::composeCachePath( $fileHash );

        $cacheTree = implode( DIRECTORY_SEPARATOR, $cacheTreeAr );

        return AbstractFilesStorage::getStorageCachePath() . DIRECTORY_SEPARATOR . $cacheTree . AbstractFilesStorage::OBJECTS_SAFE_DELIMITER . $sourceLang;
    }

    /**
     * @param array $file
     *
     * @return string
     */
    private function getGDriveFilePath( array $file ): string {

        $fileName     = $file[ self::FILE_NAME ];
        $cacheFileDir = $this->getCacheFileDir( $file );

        return $cacheFileDir . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . $fileName;
    }

    /**
     * @param array $file
     *
     * @return string
     */
    private function getGDriveFilePathForS3( array $file ): string {

        $fileName     = $file[ self::FILE_NAME ];
        $cacheFileDir = $this->getCacheFileDir( $file );

        return $cacheFileDir . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . $fileName;
    }

    /**
     * @param string                           $guid
     * @param string                           $source_lang
     * @param string                           $target_lang
     * @param string|null                      $seg_rule
     * @param FiltersConfigTemplateStruct|null $filters_extraction_parameters
     */
    public function setConversionParams( string $guid, string $source_lang, string $target_lang, ?string $seg_rule = null, ?FiltersConfigTemplateStruct $filters_extraction_parameters = null ) {
        $this->guid                          = $guid;
        $this->source_lang                   = $source_lang;
        $this->target_lang                   = $target_lang;
        $this->seg_rule                      = $seg_rule;
        $this->filters_extraction_parameters = $filters_extraction_parameters;
    }

    /**
     * @param int           $fileId
     * @param string        $remoteFileId
     * @param Google_Client $gClient
     *
     * @throws Exception
     */
    public function createRemoteFile( int $fileId, string $remoteFileId, Google_Client $gClient ) {
        $this->getService( $gClient );
        RemoteFiles_RemoteFileDao::insert( $fileId, 0, $remoteFileId, $this->serviceStruct->id, 1 );
    }

    /**
     *
     * Creates copies of the original remote file there the translation will be saved.
     *
     * @param int           $id_file
     * @param int           $id_job
     * @param Google_Client $gClient
     *
     * @throws Exception
     */
    public function createRemoteCopiesWhereToSaveTranslation( int $id_file, int $id_job, Google_Client $gClient ) {

        $service = $this->getService( $gClient );

        if ( !$service ) {
            throw new Exception( 'Cannot instantiate service' );
        }

        $listRemoteFiles = RemoteFiles_RemoteFileDao::getByFileId( $id_file, 1 );
        $remoteFile      = $listRemoteFiles[ 0 ];

        $gdriveFile = $service->files->get( $remoteFile->remote_id );
        $fileTitle  = $gdriveFile->getName();

        $job                 = Jobs_JobDao::getById( $id_job )[ 0 ];
        $translatedFileTitle = $fileTitle . ' - ' . $job->target;

        $remoteFileService = $this->buildRemoteFile( $gClient );
        $copiedFile        = $remoteFileService->copyFile( $remoteFile->remote_id, $translatedFileTitle );

        RemoteFiles_RemoteFileDao::insert( $id_file, $id_job, $copiedFile->id, $this->serviceStruct->id );

        $this->grantFileAccessByUrl( $copiedFile->id, $gClient );
    }

    /**
     * @param string        $googleFileId
     * @param Google_Client $gClient
     *
     * @return Google_Service_Drive_Permission
     * @throws Exception
     */
    public function grantFileAccessByUrl( string $googleFileId, Google_Client $gClient ): Google_Service_Drive_Permission {
        if ( !$this->session[ 'user' ] ) {
            throw new Exception( 'Cannot proceed without a User' );
        }

        $urlPermission = new Google_Service_Drive_Permission();
        $urlPermission->setType( 'anyone' );
        $urlPermission->setRole( 'reader' );

        $service = $this->getService( $gClient );

        if ( !$service ) {
            throw new Exception( 'Cannot instantiate service' );
        }

        return $service->permissions->create( $googleFileId, $urlPermission );
    }

    /**
     * @param string        $googleFileId
     * @param Google_Client $gClient
     *
     * @throws Exception
     */
    public function importFile( string $googleFileId, Google_Client $gClient ) {

        if ( !isset( $this->guid ) ) {
            throw new Exception( 'conversion params not set' );
        }

        $service = $this->getService( $gClient );

        if ( !$service ) {
            throw new Exception( 'Cannot instantiate service' );
        }

        // get meta and mimetype
        $meta = $service->files->get( $googleFileId );
        $mime = RemoteFileService::officeMimeFromGoogle( $meta->mimeType );

        // get filename
        $fileName       = $this->sanitizeFileName( $meta->getName() );
        $file_extension = RemoteFileService::officeExtensionFromMime( $mime );

        // add the extension to filename
        if ( substr( $fileName, -5 ) !== $file_extension ) {
            $fileName .= $file_extension;
        }

        // export the file
        $optParams = [
                'alt' => 'media'
        ];
        /** @var Response $file */
        $file = $service->files->export( $googleFileId, $mime, $optParams );

        if ( $file->getStatusCode() === 200 ) {
            $directory = Utils::uploadDirFromSessionCookie( $this->guid );

            if ( !is_dir( $directory ) ) {
                mkdir( $directory, 0755, true );
            }

            $filePath = Utils::uploadDirFromSessionCookie( $this->guid, $fileName );

            $size    = $file->getBody()->getSize();
            $content = $file->getBody()->read( $size );
            $saved   = file_put_contents( $filePath, $content );

            if ( $saved !== false ) {
                $generatedSha = $this->doConversion( $fileName );
                if ( empty( $generatedSha ) ) {
                    throw new Exception( 'Error when converting file.' );
                }
                $this->addFiles( $googleFileId, $fileName, $generatedSha );
            } else {
                throw new Exception( 'Error when saving file.' );
            }
        } else {
            throw new Exception( 'Error when downloading file.' );
        }
    }

    /**
     * @param string $fileName
     *
     * @return string|string[]
     */
    private function sanitizeFileName( string $fileName ) {
        return str_replace( '/', '_', $fileName );
    }

    /**
     * @param string $file_name
     *
     * @return array
     * @throws Exception
     */
    public function doConversion( string $file_name ): array {

        $uploadTokenValue = $this->guid;

        $uploadDir = INIT::$UPLOAD_REPOSITORY .
                DIRECTORY_SEPARATOR . $uploadTokenValue;

        $errDir = INIT::$STORAGE_DIR .
                DIRECTORY_SEPARATOR .
                'conversion_errors' .
                DIRECTORY_SEPARATOR . $uploadTokenValue;

        $this->featureSet = new FeatureSet();
        $this->featureSet->loadFromUserEmail( $this->session[ 'user' ]->email );

        $converter = new FilesConverter(
                [ $file_name ],
                $this->source_lang,
                $this->target_lang,
                $uploadDir,
                $errDir,
                $uploadTokenValue,
                $this->seg_rule,
                $this->featureSet,
                $this->filters_extraction_parameters,
        );

        $converter->convertFiles();

        $result = $converter->getResult();

        if ( $result->hasErrors() ) {
            throw new RuntimeException( $result->getErrors()[ 0 ] );
        }

        $data = [];
        foreach ( $result->getData() as $value ) {
            $data = [ 'cacheHash' => $value->getCacheHash(), 'diskHash' => $value->getDiskHash() ];
        }

        return $data;

    }

}