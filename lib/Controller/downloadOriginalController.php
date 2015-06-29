<?php

set_time_limit( 180 );

class downloadOriginalController extends downloadController {

    private $id_job;
    private $password;
    private $fname;
    private $download_type;
    private $id_file;


    public function __construct() {

        $filterArgs = array(
                'filename'      => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW
                ),
                'id_file'       => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'id_job'        => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'download_type' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'password'      => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                )
        );

        $__postInput = filter_var_array( $_REQUEST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->fname         = $__postInput[ 'filename' ];
        $this->id_file       = $__postInput[ 'id_file' ];
        $this->id_job        = $__postInput[ 'id_job' ];
        $this->download_type = $__postInput[ 'download_type' ];
        $this->password      = $__postInput[ 'password' ];

    }

    public function doAction() {

        //get storage object
        $fs        = new FilesStorage();
        $files_job = $fs->getOriginalFilesForJob( $this->id_job, $this->id_file, $this->password );

        $output_content  = array();
        $thereIsAZipFile = false;


        foreach ( $files_job as $file ) {
            $id_file = $file[ 'id_file' ];

            $zipPathInfo = ZipArchiveExtended::zipPathInfo( $file[ 'filename' ] );
            if ( is_array( $zipPathInfo ) ) {
                $thereIsAZipFile                                 = true;
                $output_content[ $id_file ][ 'zipfilename' ]     = $zipPathInfo[ 'zipfilename' ];
                $output_content[ $id_file ][ 'zipinternalPath' ] = $zipPathInfo[ 'dirname' ];
                $output_content[ $id_file ][ 'filename' ]        = $zipPathInfo[ 'basename' ];
            } else {
                $output_content[ $id_file ][ 'filename' ] = $file[ 'filename' ];
            }

            $output_content[ $id_file ][ 'contentPath' ] = $file[ 'originalFilePath' ];
        }


        if ( $this->download_type == 'all' ) {
            if ( $thereIsAZipFile ) {
                $output_content = $this->getOutputContentsWithZipFiles( $output_content );
            }

            if ( count( $output_content ) > 1 ) {

                foreach ( $output_content as $key => $iFile ) {
                    $output_content[ $key ] = new ZipContentObject( $iFile );
                }

                $this->_filename = $this->fname;
                $pathinfo        = pathinfo( $this->fname );

                if ( $pathinfo[ 'extension' ] != 'zip' ) {
                    $this->_filename = $pathinfo[ 'basename' ] . ".zip";
                }

                $this->content = self::composeZip( $output_content ); //add zip archive content here;
            } elseif ( count( $output_content ) == 1 ) {
                $this->setContent( $output_content );
            }
        } else {
            $this->setContent( $output_content );
        }
    }

    /**
     * There is a foreach, but this should be always one element
     *
     * @param $output_content
     */
    private function setContent( $output_content ) {
        foreach ( $output_content as $oc ) {
            $this->_filename = $oc[ 'filename' ];
            if(isset($oc[ 'document_content' ]) && !empty($oc[ 'document_content' ])) {
                $this->content = $oc[ 'document_content' ];
            }
            else {
                $this->content = file_get_contents( $oc[ 'contentPath' ] );
            }
        }
    }



//    private function getOutputContentsWithZipFiles( $output_content ) {
//        $zipFiles         = array();
//        $newOutputContent = array();
//
//        //group files by zip archive
//        foreach ( $output_content as $idFile => $fileInformations ) {
//            //If this file comes from a ZIP, add it to $zipFiles
//            if ( isset( $fileInformations[ 'zipfilename' ] ) ) {
//                $zipFiles[ $fileInformations[ 'zipfilename' ] ][ ] = $fileInformations;
//                unset( $output_content[ $idFile ] );
//            }
//        }
//        unset( $idFile );
//        unset( $fileInformations );
//
//        //for each zip file index, compose zip again, save it to a temporary location and add it into output_content
//        foreach ( $zipFiles as $zipFileName => $internalFile ) {
//            foreach ( $internalFile as $__idx => $fileInformations ) {
//                $internalFile[ $__idx ][ 'filename' ] = $fileInformations[ 'zipinternalPath' ] . DIRECTORY_SEPARATOR . $fileInformations[ 'filename' ];
//            }
//
//            $zip      = $this->composeZip( $internalFile );
//            $savedZip = tempnam( "/tmp", "matecatzip" );
//
//            file_put_contents( $savedZip, $zip );
//
//            $newOutputContent[ ] = array(
//                    'filename'    => $zipFileName,
//                    'contentPath' => $savedZip
//            );
//        }
//
//        $newOutputContent = array_merge( $newOutputContent, $output_content );
//
//        return $newOutputContent;
//    }

    private static function getOutputContentsWithZipFiles( $output_content ) {
        $zipFiles         = array();
        $newOutputContent = array();

        //group files by zip archive
        foreach ( $output_content as $idFile => $fileInformations ) {
            //If this file comes from a ZIP, add it to $zipFiles
            if ( isset( $fileInformations[ 'zipfilename' ] ) ) {
                $zipFileName = $fileInformations[ 'zipfilename' ];

                $zipFiles[ $zipFileName ][ ] = $fileInformations;
                unset( $output_content[ $idFile ] );
            }
        }
        unset( $idFile );
        unset( $fileInformations );

        //for each zip file index, compose zip again, save it to a temporary location and add it into output_content
        foreach ( $zipFiles as $zipFileName => $internalFile ) {
            foreach ( $internalFile as $__idx => $fileInformations ) {
                $zipFiles[ $zipFileName ][ $__idx ][ 'output_filename' ] = $fileInformations[ 'zipinternalPath' ] . DIRECTORY_SEPARATOR . $fileInformations[ 'filename' ];

                $zipFiles[ $zipFileName ][ $__idx ][ 'document_content' ] = $internalFile[ $__idx ][ 'documentContent' ];

                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'documentContent' ] );
                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'filename' ] );
                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'zipinternalPath' ] );
                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'zipfilename' ] );
                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'source' ] );
                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'target' ] );
                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'out_xliff_name' ] );
            }

            $internalFile = $zipFiles[ $zipFileName ];
            $internalFile = self::getOutputContentsWithZipFiles( $internalFile );

            foreach ( $internalFile as $key => $iFile ) {
                $iFile[ 'input_filename' ] = $iFile[ 'contentPath' ];
                unset ( $iFile[ 'contentPath' ] );
                $internalFile[ $key ] = new ZipContentObject( $iFile );
            }

            $zip = self::composeZip( $internalFile );

            $newOutputContent[ ] = array(
                    'output_filename'  => $zipFileName,
                    'document_content' => $zip
            );
        }

        $newOutputContent = array_merge( $newOutputContent, $output_content );

        return $newOutputContent;
    }

}
