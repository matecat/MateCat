<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 27/01/14
 * Time: 18.57
 * 
 */

abstract class downloadController extends controller {

    protected $content = "";
    protected $_filename = "unknown";
    protected $_user_provided_filename ;

    protected $uid;
    protected $userIsLogged = false;
    protected $userMail;

    protected $id_job ;

    /**
     * @var Projects_ProjectStruct
     */
    protected $project ;

    protected function unlockToken( $tokenContent = null ) {

        if ( isset( $this->downloadToken ) && !empty( $this->downloadToken ) ) {
            setcookie(
                    $this->downloadToken,
                    ( empty( $tokenContent ) ? json_encode( array(
                            "code"    => 0,
                            "message" => "Download complete."
                    ) ) : json_encode( $tokenContent ) ),
                    2147483647            // expires January 1, 2038
            );
            $this->downloadToken = null;
        }

    }

    public function finalize() {
        try {
            $this->unlockToken();

            if ( empty( $this->project) ) {
                $this->project = \Projects_ProjectDao::findByJobId($this->id_job);
            }

            if ( empty($this->_filename ) ) {
                $this->_filename = $this->_getDefaultFileName( $this->project );
            }

            $isGDriveProject = \Projects_ProjectDao::isGDriveProject($this->project->id);

            $forceXliff = intval( filter_input( INPUT_GET, 'forceXliff' ) );

            if( !$isGDriveProject || $forceXliff === 1 ) {
                $buffer = ob_get_contents();
                ob_get_clean();
                ob_start("ob_gzhandler");  // compress page before sending
                $this->nocache();
                header("Content-Type: application/force-download");
                header("Content-Type: application/octet-stream");
                header("Content-Type: application/download");
                header("Content-Disposition: attachment; filename=\"$this->_filename\""); // enclose file name in double quotes in order to avoid duplicate header error. Reference https://github.com/prior/prawnto/pull/16
                header("Expires: 0");
                header("Connection: close");
                echo $this->content;
                exit;
            }
        } catch (Exception $e) {
            echo "<pre>";
            print_r($e);
            echo "\n\n\n";
            echo "</pre>";
            exit;
        }
    }

    /**
     * If more than one file constitutes the project, then the filename is the project name.
     * If the project is made of just one file, then the filename for download is the file name itself.
     * @param $project Projects_ProjectStruct
     */
    protected function _getDefaultFileName( Projects_ProjectStruct $project ) {
            $files = Files_FileDao::getByProjectId( $project->id );

            if ( count(  $files ) > 1 ) {
                return $this->project->name . ".zip" ;
            } else {
                return $files[0]->filename ;
            }
        }

    /**
     * @param ZipContentObject[] $output_content
     * @param string $outputFile
     *
     * @return string The zip binary
     */
    protected static function composeZip( Array $output_content, $outputFile=null , $isOriginalFile=false) {
        if(empty($outputFile)){
            $outputFile = tempnam("/tmp", "zipmatecat");
        }

        $zip  = new ZipArchive();
        $zip->open( $outputFile, ZipArchive::OVERWRITE );

        $rev_index_name = array();

        foreach ( $output_content as $f ) {

            //Php Zip bug, utf-8 not supported
            $fName = preg_replace( '/[^0-9a-zA-Z_\.\-=\$\:@§]/u', "_", $f->output_filename );
            $fName = preg_replace( '/[_]{2,}/', "_", $fName );
            $fName = str_replace( '_.', ".", $fName );

            if($isOriginalFile!=true) {
                $fName = self::sanitizeFileExtension( $fName );
            }

            $nFinfo = FilesStorage::pathinfo_fix( $fName );
            $_name  = $nFinfo[ 'filename' ];
            if ( strlen( $_name ) < 3 ) {
                $fName = substr( uniqid(), -5 ) . "_" . $fName;
            }

            if ( array_key_exists( $fName, $rev_index_name ) ) {
                $fName = uniqid() . $fName;
            }

            $rev_index_name[ $fName ] = $fName;

            $content = $f->getContent();
            if( !empty( $content ) ){
                $zip->addFromString( $fName, $content);
            }
        }

        // Close and send to users
        $zip->close();
        $zip_content = file_get_contents( $outputFile );
        unlink( $outputFile );

        return $zip_content;
    }

    protected static function sanitizeFileExtension( $filename ) {

        $pathinfo = FilesStorage::pathinfo_fix( $filename );

        switch (strtolower( $pathinfo[ 'extension' ] )) {
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

    public function checkLogin( $close = true ) {
        //Warning, sessions enabled, disable them after check, $_SESSION is in read only mode after disable
        parent::sessionStart();
        $this->userIsLogged = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) );
        $this->userMail     = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) ? $_SESSION[ 'cid' ] : null );
        $this->uid          = ( isset( $_SESSION[ 'uid' ] ) && !empty( $_SESSION[ 'uid' ] ) ? $_SESSION[ 'uid' ] : null );

        if ( $close ) {
            parent::disableSessions();
        }

    }

}
