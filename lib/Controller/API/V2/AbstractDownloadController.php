<?php

namespace API\V2;

use AbstractControllers\AbstractStatefulKleinController;
use CookieManager;
use Exception;
use Files\FileDao;
use FilesStorage\AbstractFilesStorage;
use INIT;
use Jobs_JobDao;
use Jobs_JobStruct;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use ReflectionException;
use ZipArchive;
use ZipContentObject;

abstract class AbstractDownloadController extends AbstractStatefulKleinController {
    public int $id_job;
    public string $password;

    protected string $outputContent = "";
    protected string $_filename     = "";

    protected static string $ZIP_ARCHIVE  = "application/zip";
    protected static string $XLIFF_FILE   = "application/xliff+xml";
    protected static string $OCTET_STREAM = "application/octet-stream";
    protected string        $mimeType     = "application/octet-stream";


    protected ?string $_user_provided_filename = null;

    /**
     * @var Jobs_JobStruct
     */
    protected Jobs_JobStruct $job;

    /**
     * @param int $ttl
     *
     * @return Jobs_JobStruct
     * @throws ReflectionException
     */
    public function getJob( int $ttl = 0 ): Jobs_JobStruct {
        if ( empty( $this->job ) ) {
            $this->job = Jobs_JobDao::getById( $this->id_job, $ttl )[ 0 ];
        }

        return $this->job;
    }

    /**
     * @param ZipContentObject $content
     *
     * @return $this
     * @throws Exception
     */
    public function setOutputContent( ZipContentObject $content ): AbstractDownloadController {
        $this->outputContent = $content->getContent();

        return $this;
    }

    protected function setMimeType() {

        $extension = AbstractFilesStorage::pathinfo_fix( $this->_filename, PATHINFO_EXTENSION );

        switch ( strtolower( $extension ) ) {
            case "xlf":
            case "sdlxliff":
            case "xliff":
                $this->mimeType = self::$XLIFF_FILE;
                break;
            case "zip":
                $this->mimeType = self::$ZIP_ARCHIVE;
                break;
            default:
                $this->mimeType = self::$OCTET_STREAM;
                break;

        }
    }

    /**
     * @param string $filename
     *
     * @return $this
     */
    public function setFilename( string $filename ): AbstractDownloadController {
        $this->_filename = $filename;

        return $this;
    }

    /**
     * @return string
     */
    public function getFilename(): string {
        return $this->_filename;
    }

    /**
     * @var Projects_ProjectStruct
     */
    protected Projects_ProjectStruct $project;

    /**
     * @return Projects_ProjectStruct
     */
    public function getProject(): Projects_ProjectStruct {
        return $this->project;
    }

    protected function unlockToken( $tokenContent = null ) {

        if ( !empty( $this->downloadToken ) ) {
            CookieManager::setCookie( $this->downloadToken,
                    ( empty( $tokenContent ) ? json_encode( [
                            "code"    => 0,
                            "message" => "Download complete."
                    ] ) : json_encode( $tokenContent ) ),
                    [
                            'expires'  => time() + 600,
                            'path'     => '/',
                            'domain'   => INIT::$COOKIE_DOMAIN,
                            'secure'   => true,
                            'httponly' => false,
                            'samesite' => 'None',
                    ]
            );
            $this->downloadToken = null;
        }
    }

    /**
     * Set No Cache headers
     *
     */
    protected function nocache() {
        header( "Expires: Tue, 03 Jul 2001 06:00:00 GMT" );
        header( "Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . " GMT" );
        header( "Cache-Control: no-store, no-cache, must-revalidate, max-age=0" );
        header( "Cache-Control: post-check=0, pre-check=0", false );
        header( "Pragma: no-cache" );
    }

    /**
     * Download the file
     *
     * @param bool $forceXliff
     */
    public function finalize( bool $forceXliff = false ) {
        try {
            $this->unlockToken();

            if ( empty( $this->project ) ) {
                $this->project = Projects_ProjectDao::findByJobId( $this->id_job );
            }

            if ( empty( $this->_filename ) ) {
                $this->_filename = $this->getDefaultFileName( $this->project );
            }

            $isGDriveProject = Projects_ProjectDao::isGDriveProject( $this->project->id );

            if ( !$isGDriveProject || $forceXliff === true ) {
                ob_get_contents();
                ob_get_clean();
                ob_start( "ob_gzhandler" );  // compress page before sending
                $this->nocache();

                $filename = $this->_filename;
                if($this->encoding === "base64"){
                    $filename = base64_encode($filename);
                }

                header( "Content-Type: $this->mimeType" );
                header( "Content-Disposition: attachment; filename=\"$filename\"" ); // enclose file name in double quotes in order to avoid duplicate header error. Reference https://github.com/prior/prawnto/pull/16
                header( "Expires: 0" );
                header( "Connection: close" );
                header( "Content-Length: " . strlen( $this->outputContent ) );
                echo $this->outputContent;
                exit;
            }
        } catch ( Exception $e ) {
            echo "<pre>";
            print_r( $e );
            echo "\n\n\n";
            echo "</pre>";
            exit;
        }
    }

    /**
     * If more than one file constitutes the project, then the filename is the project name.
     * If the project is made of just one file, then the filename for download is the file name itself.
     *
     * @param $project Projects_ProjectStruct
     *
     * @return string
     * @throws ReflectionException
     */
    public function getDefaultFileName( Projects_ProjectStruct $project ): string {
        $files = FileDao::getByProjectId( $project->id );

        if ( count( $files ) > 1 ) {
            return $this->project->name . ".zip";
        } else {
            return $files[ 0 ]->filename;
        }
    }

    /**
     * @param ZipContentObject[] $output_content
     * @param string|null        $outputFile
     *
     * @param ?bool              $isOriginalFile
     *
     * @return string The zip binary
     * @throws Exception
     */
    protected static function composeZip( array $output_content, ?string $outputFile = null, ?bool $isOriginalFile = false ): string {
        if ( empty( $outputFile ) ) {
            $outputFile = tempnam( "/tmp", "zipmatecat" );
        }

        $zip = new ZipArchive();
        $zip->open( $outputFile, ZipArchive::OVERWRITE );

        $rev_index_name            = [];
        $rev_index_duplicate_count = [];

        foreach ( $output_content as $f ) {

            $fName = $f->output_filename;

            if ( !$isOriginalFile ) {
                $fName = self::forceOcrExtension( $fName );
            }

            // avoid collisions
            if ( array_key_exists( $fName, $rev_index_name ) ) {
                $rev_index_duplicate_count[ $fName ] = isset( $rev_index_duplicate_count[ $fName ] ) ? $rev_index_duplicate_count[ $fName ] + 1 : 1;

                $fName = $fName . "(" . $rev_index_duplicate_count[ $fName ] . ")";
            }

            $rev_index_name[ $fName ] = $fName;

            $content = $f->getContent();
            if ( !empty( $content ) ) {
                $zip->addFromString( $fName, $content );
            }
        }

        // Close and send to users
        $zip->close();
        $zip_content = file_get_contents( $outputFile );
        unlink( $outputFile );

        return $zip_content;
    }

    /**
     * @param string $filename
     *
     * @return string
     */
    public static function forceOcrExtension( string $filename ): string {

        $pathinfo = AbstractFilesStorage::pathinfo_fix( $filename );

        switch ( strtolower( $pathinfo[ 'extension' ] ) ) {
            case 'pdf':
            case 'bmp':
            case 'png':
            case 'gif':
            case 'jpg':
            case 'jpeg':
            case 'tiff':
            case 'tif':
                $filename = $pathinfo[ 'basename' ] . ".docx";
                break;
        }

        return $filename;
    }
}