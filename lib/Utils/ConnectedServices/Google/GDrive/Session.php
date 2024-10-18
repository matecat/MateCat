<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/11/2016
 * Time: 15:28
 */

namespace ConnectedServices\Google\GDrive;

use API\Commons\Exceptions\AuthenticationError;
use ArrayObject;
use ConnectedServices\ConnectedServiceDao;
use ConnectedServices\ConnectedServiceStruct;
use Constants;
use ConversionHandler;
use DirectoryIterator;
use Exception;
use Exceptions\NotFoundException;
use Exceptions\ValidationError;
use FeatureSet;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use FilesStorage\S3FilesStorage;
use FilesystemIterator;
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
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;
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

    protected string  $guid;
    protected string  $source_lang;
    protected string  $target_lang;
    protected ?string $seg_rule = null;

    protected array $session = [];
    protected       $filters_extraction_parameters;

    /**
     * @var Google_Service_Drive|null
     */
    protected ?Google_Service_Drive $service = null;
    protected                       $token;

    /**
     * @var ?ConnectedServiceStruct
     */
    protected ?ConnectedServiceStruct $serviceStruct = null;

    /**
     * @var AbstractFilesStorage
     */
    protected $files_storage;

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
     * This class overrides a not existent super global when called by CLI
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
     * @param $newSourceLang
     * @param $originalSourceLang
     *
     * @return bool
     * @throws Exception
     */
    public function changeSourceLanguage( $newSourceLang, $originalSourceLang ): bool {
        $fileList = $this->session[ self::FILE_LIST ];
        $success  = true;

        $this->renameTheFileMap( $newSourceLang, $originalSourceLang );

        foreach ( $fileList as $fileId => $file ) {

            if ( $success ) {

                $fileHash = $file[ self::FILE_HASH ];

                if ( $newSourceLang !== $originalSourceLang ) {

                    $originalCacheFileDir = $this->getCacheFileDir( $file, $originalSourceLang );
                    $newCacheFileDir      = $this->getCacheFileDir( $file, $newSourceLang );

                    $renameDirSuccess     = false;
                    $renameFileRefSuccess = false;

                    if ( AbstractFilesStorage::isOnS3() ) {

                        // copy orig and cache\INIT::$UPLOAD_REPOSITORY folder
                        $s3Client = S3FilesStorage::getStaticS3Client();
                        $copyOrig = $s3Client->copyFolder( [
                                'source_bucket' => INIT::$AWS_STORAGE_BASE_BUCKET,
                                'source_folder' => $originalCacheFileDir . '/orig',
                                'target_folder' => $newCacheFileDir . '/orig',
                                'delete_source' => false,
                        ] );

                        $copyWork = $s3Client->copyFolder( [
                                'source_bucket' => INIT::$AWS_STORAGE_BASE_BUCKET,
                                'source_folder' => $originalCacheFileDir . '/work',
                                'target_folder' => $newCacheFileDir . '/work',
                                'delete_source' => false,
                        ] );

                        if ( $copyOrig and $copyWork ) {
                            $renameDirSuccess     = true;
                            $renameFileRefSuccess = true;
                        }

                    } else {

                        $renameDirSuccess = rename( $originalCacheFileDir, $newCacheFileDir );

                        $uploadDir = $this->getUploadDir();

                        $originalUploadRefFile = $uploadDir . DIRECTORY_SEPARATOR . $fileHash . '|' . $originalSourceLang;
                        $newUploadRefFile      = $uploadDir . DIRECTORY_SEPARATOR . $fileHash . '|' . $newSourceLang;

                        $renameFileRefSuccess = rename( $originalUploadRefFile, $newUploadRefFile );

                    }

                    if ( !$renameDirSuccess || !$renameFileRefSuccess ) {
                        Log::doJsonLog( 'Error when moving cache file dir to ' . $newCacheFileDir );
                        $success = false;
                    }
                }
            }
        }

        return $success;
    }

    /**
     * Rename the filemap in session folder stored on filesystem
     *
     * ----------------------------------------------------------------------
     *
     * Example:
     *
     * 2344e5918dcff468b4362d79cb16b0039c77d608|af-ZA ---> 2344e5918dcff468b4362d79cb16b0039c77d608|it-IT
     *
     * @param string $newSourceLang
     * @param string $originalSourceLang
     */
    private function renameTheFileMap( string $newSourceLang, string $originalSourceLang ) {
        $uploadDir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $this->session[ 'upload_session' ];

        /** @var DirectoryIterator $item */
        foreach (
                new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator( $uploadDir, FilesystemIterator::SKIP_DOTS ),
                        RecursiveIteratorIterator::SELF_FIRST ) as $item
        ) {
            $originalSourceLangMarker = '|' . $originalSourceLang;
            $newSourceLangMarker      = '|' . $newSourceLang;

            if ( AbstractFilesStorage::fileEndsWith( $item->getBasename(), $originalSourceLangMarker ) ) {
                $newSourceLangFileMap = str_replace( $originalSourceLangMarker, $newSourceLangMarker, $item->getBasename() );
                rename( $item->getBasename(), $newSourceLangFileMap );
            }
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getFileStructureForJsonOutput(): array {
        $response = [];

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

                $mime                  = include __DIR__ . '/../../../Utils/Mime2Extension.php';
                $response[ 'files' ][] = [
                        'fileId'        => $fileId,
                        'fileName'      => $fileName,
                        'fileSize'      => $s3[ 'ContentLength' ],
                        'fileExtension' => $mime[ $s3[ 'ContentType' ] ][ 0 ]
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
     * @param string $fileHash
     */
    public function addFiles( string $fileId, string $fileName, string $fileHash ) {

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
     * @param $session
     *
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
     * @param $fileName
     *
     * @return int|string|null
     */
    public function findFileIdByName( string $fileName ) {
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
     *
     * @return bool
     * @throws ConnectionException
     * @throws ReflectionException
     */
    public function removeFile( string $fileId ): bool {
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

            $tempUploadedFileDir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $this->session[ 'upload_session' ];

            /** @var DirectoryIterator $item */
            foreach ( $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $tempUploadedFileDir, RecursiveDirectoryIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST ) as $item ) {
                $target   = explode( '__', $pathCache );
                $hashFile = $file[ 'fileHash' ] . "|" . end( $target );

                if ( $item->getFilename() === $file[ 'fileName' ] or $item->getFilename() === $hashFile ) {
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
     * @throws Exception
     */
    public function removeAllFiles() {
        foreach ( $this->session[ self::FILE_LIST ] as $singleFileId => $file ) {
            $this->removeFile( $singleFileId );
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
     * @return string
     */
    private function getUploadDir(): string {
        return INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . filter_input( INPUT_COOKIE, 'upload_session' );
    }

    /**
     * @param array   $file
     * @param ?string $lang
     *
     * @return string
     */
    private function getCacheFileDir( array $file, ?string $lang = '' ): string {
        $sourceLang = $this->session[ Constants::SESSION_ACTUAL_SOURCE_LANG ];

        if ( $lang !== '' ) {
            $sourceLang = $lang;
        }

        $fileHash = $file[ self::FILE_HASH ];

        $fs          = $this->files_storage;
        $cacheTreeAr = $fs::composeCachePath( $fileHash );

        $cacheTree = implode( DIRECTORY_SEPARATOR, $cacheTreeAr );

        if ( AbstractFilesStorage::isOnS3() ) {
            return S3FilesStorage::CACHE_PACKAGE_FOLDER . DIRECTORY_SEPARATOR . $cacheTree . S3FilesStorage::OBJECTS_SAFE_DELIMITER . $sourceLang;
        }

        return INIT::$CACHE_REPOSITORY . DIRECTORY_SEPARATOR . $cacheTree . "|" . $sourceLang;
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
     * @param string      $guid
     * @param string      $source_lang
     * @param string      $target_lang
     * @param string|null $seg_rule
     * @param null        $filters_extraction_parameters
     */
    public function setConversionParams( string $guid, string $source_lang, string $target_lang, ?string $seg_rule = null, $filters_extraction_parameters = null ) {
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
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ValidationError
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
                $fileHash = sha1_file( $filePath );

                $this->addFiles( $googleFileId, $fileName, $fileHash );
                $this->doConversion( $fileName );
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
     * @return void
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ValidationError
     */
    private function doConversion( string $file_name ): void {

        $uploadDir = $this->guid;

        $intDir = INIT::$UPLOAD_REPOSITORY .
                DIRECTORY_SEPARATOR . $uploadDir;

        $errDir = INIT::$STORAGE_DIR .
                DIRECTORY_SEPARATOR .
                'conversion_errors' .
                DIRECTORY_SEPARATOR . $uploadDir;

        $conversionHandler = new ConversionHandler();
        $conversionHandler->setFileName( $file_name );
        $conversionHandler->setSourceLang( $this->source_lang );
        $conversionHandler->setTargetLang( $this->target_lang );
        $conversionHandler->setSegmentationRule( $this->seg_rule );
        $conversionHandler->setCookieDir( $uploadDir );
        $conversionHandler->setIntDir( $intDir );
        $conversionHandler->setErrDir( $errDir );

        $this->featureSet = new FeatureSet();
        $this->featureSet->loadFromUserEmail( $this->session[ 'user' ]->email );
        $conversionHandler->setFeatures( $this->featureSet );
        $conversionHandler->setUserIsLogged( true );

        $conversionHandler->doAction();

    }
}