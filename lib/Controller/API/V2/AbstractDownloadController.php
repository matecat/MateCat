<?php

namespace API\V2;

use API\App\AbstractStatefulKleinController;
use AuthCookie;
use Bootstrap;
use CookieManager;
use Database;
use Exception;
use Files_FileDao;
use FilesStorage\AbstractFilesStorage;
use INIT;
use Jobs_JobDao;
use Jobs_JobStruct;
use Log;
use Projects_ProjectStruct;
use Users_UserDao;
use Users_UserStruct;
use ZipContentObject;

abstract class AbstractDownloadController extends AbstractStatefulKleinController
{
    public $id_job;
    public $password;

    protected $outputContent = "";
    protected $_filename     = "";

    protected static $ZIP_ARCHIVE  = "application/zip";
    protected static $XLIFF_FILE   = "application/xliff+xml";
    protected static $OCTET_STREAM = "application/octet-stream";
    protected        $mimeType     = "application/octet-stream";


    protected $_user_provided_filename;

    /**
     * @var Jobs_JobStruct
     */
    protected $job;

    /**
     * @throws Exception
     */
    protected function sessionStart() {
        Bootstrap::sessionStart();
    }

    /**
     * Explicitly disable sessions for ajax call
     *
     * Sessions enabled on INIT Class
     *
     */
    protected function disableSessions() {
        Bootstrap::sessionClose();
    }

    protected function setUserCredentials() {

        $this->user        = new Users_UserStruct();
        $this->user->uid   = ( isset( $_SESSION[ 'uid' ] ) && !empty( $_SESSION[ 'uid' ] ) ? $_SESSION[ 'uid' ] : null );
        $this->user->email = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) ? $_SESSION[ 'cid' ] : null );

        try {

            $userDao            = new Users_UserDao( Database::obtain() );
            $loggedUser         = $userDao->setCacheTTL( 3600 )->read( $this->user )[ 0 ]; // one hour cache
            $this->userIsLogged = (
                !empty( $loggedUser->uid ) &&
                !empty( $loggedUser->email ) &&
                !empty( $loggedUser->first_name ) &&
                !empty( $loggedUser->last_name )
            );

        } catch ( Exception $e ) {
            Log::doJsonLog( 'User not logged.' );
        }

        $this->user = ( $this->userIsLogged ? $loggedUser : $this->user );
    }

    public function readLoginInfo( $close = true ) {
        //Warning, sessions enabled, disable them after check, $_SESSION is in read only mode after disable
        self::sessionStart();
        $this->_setUserFromAuthCookie();
        $this->setUserCredentials();

        if ( $close ) {
            self::disableSessions();
        }
    }

    /**
     *  Try to get user name from cookie if it is not present and put it in session.
     *
     */
    protected function _setUserFromAuthCookie() {
        if ( empty( $_SESSION[ 'cid' ] ) ) {
            $username_from_cookie = AuthCookie::getCredentials();
            if ( $username_from_cookie ) {
                $_SESSION[ 'cid' ] = $username_from_cookie[ 'username' ];
                $_SESSION[ 'uid' ] = $username_from_cookie[ 'uid' ];
            }
        }
    }

    /**
     * @param int $ttl
     *
     * @return Jobs_JobStruct
     */
    public function getJob( $ttl = 0 ) {
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
    public function setOutputContent( ZipContentObject $content ) {
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
    public function setFilename( $filename ) {
        $this->_filename = $filename;

        return $this;
    }

    /**
     * @return string
     */
    public function getFilename() {
        return $this->_filename;
    }

    /**
     * @var Projects_ProjectStruct
     */
    protected $project;

    /**
     * @return Projects_ProjectStruct
     */
    public function getProject() {
        return $this->project;
    }

    protected function unlockToken( $tokenContent = null ) {

        if ( isset( $this->downloadToken ) && !empty( $this->downloadToken ) ) {
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
     * @param bool $forceXliff
     */
    public function finalize($forceXliff = false) {
        try {
            $this->unlockToken();

            if ( empty( $this->project ) ) {
                $this->project = \Projects_ProjectDao::findByJobId( $this->id_job );
            }

            if ( empty( $this->_filename ) ) {
                $this->_filename = $this->getDefaultFileName( $this->project );
            }

            $isGDriveProject = \Projects_ProjectDao::isGDriveProject( $this->project->id );

            if ( !$isGDriveProject || $forceXliff === true ) {
                $buffer = ob_get_contents();
                ob_get_clean();
                ob_start( "ob_gzhandler" );  // compress page before sending
                $this->nocache();
                header( "Content-Type: $this->mimeType" );
                header( "Content-Disposition: attachment; filename=\"$this->_filename\"" ); // enclose file name in double quotes in order to avoid duplicate header error. Reference https://github.com/prior/prawnto/pull/16
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
     */
    public function getDefaultFileName( Projects_ProjectStruct $project ) {
        $files = Files_FileDao::getByProjectId( $project->id );

        if ( count( $files ) > 1 ) {
            return $this->project->name . ".zip";
        } else {
            return $files[ 0 ]->filename;
        }
    }

    /**
     * @param ZipContentObject[] $output_content
     * @param string             $outputFile
     *
     * @param bool               $isOriginalFile
     *
     * @return string The zip binary
     * @throws Exception
     */
    protected static function composeZip( Array $output_content, $outputFile = null, $isOriginalFile = false ) {
        if ( empty( $outputFile ) ) {
            $outputFile = tempnam( "/tmp", "zipmatecat" );
        }

        $zip = new \ZipArchive();
        $zip->open( $outputFile, \ZipArchive::OVERWRITE );

        $rev_index_name = [];
        $rev_index_duplicate_count = [];

        foreach ( $output_content as $f ) {

            $fName = $f->output_filename ;

            if ( $isOriginalFile != true ) {
                $fName = self::forceOcrExtension( $fName );
            }

            // avoid collisions
            if ( array_key_exists( $fName, $rev_index_name ) ) {
                $rev_index_duplicate_count[ $fName ] = isset($rev_index_duplicate_count[ $fName ]) ? $rev_index_duplicate_count[ $fName ] + 1 : 1;

                $fName = $fName . "(".$rev_index_duplicate_count[ $fName ].")";
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
     * @param $filename
     * @return string
     */
    public static function forceOcrExtension( $filename ) {

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