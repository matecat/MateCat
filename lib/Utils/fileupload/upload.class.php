<?php

/*
 * jQuery File Upload Plugin PHP Class 5.11.2
 * https://github.com/blueimp/jQuery-File-Upload
 *
 * Copyright 2010, Sebastian Tschan
 * https://blueimp.net
 *
 * Licensed under the MIT license:
 * http://www.opensource.org/licenses/MIT
 */
define( "DIRSEP", "//" );

class UploadHandler {

    protected $options;
    protected $acceptedMime=array();
    protected $acceptedExtensions=array();


    function __construct( $options = null ) {

        //Mime White List, take them from ProjectManager.php
        foreach ( INIT::$MIME_TYPES as $key=>$value ) {
            foreach ( INIT::$SUPPORTED_FILE_TYPES as $key2 => $value2 ) {
                if (count(array_intersect(array_keys($value2), array_values($value)))>0)
                {
                    array_push($this->acceptedMime, $key);
                    break;
                }
            }
        }
        foreach ( INIT::$SUPPORTED_FILE_TYPES as $extensions ) {
            $this->acceptedExtensions += $extensions;
        }

        $this->options = array(
                'script_url'              => $this->getFullUrl() . '/',
                'upload_dir'              => Utils::uploadDirFromSessionCookie($_COOKIE['upload_session']),
                'upload_url'              => $this->getFullUrl() . '/files/',
                'param_name'              => 'files',
                // Set the following option to 'POST', if your server does not support
                // DELETE requests. This is a parameter sent to the client:
                'delete_type'             => "", //'DELETE',
                // The php.ini settings upload_max_filesize and post_max_size
                // take precedence over the following max_file_size setting:
                'max_file_size'           => null,
                'min_file_size'           => 1,
                'accept_file_types'       => '/.+$/i',
                // The maximum number of files for the upload directory:
                'max_number_of_files'     => null,
                // Image resolution restrictions:
                'max_width'               => null,
                'max_height'              => null,
                'min_width'               => 1,
                'min_height'              => 1,
                // Set the following option to false to enable resumable uploads:
                'discard_aborted_uploads' => true,
                // Set to true to rotate images based on EXIF meta data, if available:
                'orient_image'            => false,
                'image_versions'          => array(
                    // Uncomment the following version to restrict the size of
                    // uploaded images. You can also add additional versions with
                    // their own upload directories:
                    /*
                      'large' => array(
                      'upload_dir' => dirname($_SERVER['SCRIPT_FILENAME']).'/files/',
                      'upload_url' => $this->getFullUrl().'/files/',
                      'max_width' => 1920,
                      'max_height' => 1200,
                      'jpeg_quality' => 95
                      ),
                     */
                    'thumbnail' => array(
                            'upload_dir' => dirname( $_SERVER[ 'SCRIPT_FILENAME' ] ) . '/thumbnails/',
                            'upload_url' => $this->getFullUrl() . '/thumbnails/',
                            'max_width'  => 80,
                            'max_height' => 80
                    )
                )
        );
        if ( $options ) {
            $this->options = array_replace_recursive( $this->options, $options );
        }
    }

    protected function getFullUrl() {
        $https = !empty( $_SERVER[ 'HTTPS' ] ) && $_SERVER[ 'HTTPS' ] !== 'off';

        return
                ( $https ? 'https://' : 'http://' ) .
                ( !empty( $_SERVER[ 'REMOTE_USER' ] ) ? $_SERVER[ 'REMOTE_USER' ] . '@' : '' ) .
                ( isset( $_SERVER[ 'HTTP_HOST' ] ) ? $_SERVER[ 'HTTP_HOST' ] : ( $_SERVER[ 'SERVER_NAME' ] .
                        ( $https && $_SERVER[ 'SERVER_PORT' ] === 443 ||
                        $_SERVER[ 'SERVER_PORT' ] === 80 ? '' : ':' . $_SERVER[ 'SERVER_PORT' ] ) ) ) .
                substr( $_SERVER[ 'SCRIPT_NAME' ], 0, strrpos( $_SERVER[ 'SCRIPT_NAME' ], '/' ) );
    }

    protected function set_file_delete_url( $file ) {
        $file->delete_url  = $this->options[ 'script_url' ]
                . '?file=' . rawurlencode( $file->name );
        $file->delete_type = $this->options[ 'delete_type' ];
        if ( $file->delete_type !== 'DELETE' ) {
            $file->delete_url .= '&_method=DELETE';
        }
    }

    protected function get_file_object( $file_name ) {
        $file_path = $this->options[ 'upload_dir' ] . $file_name;
        if ( is_file( $file_path ) && $file_name[ 0 ] !== '.' ) {
            $file       = new stdClass();
            $file->name = $file_name;
            $file->size = filesize( $file_path );
            $file->url  = $this->options[ 'upload_url' ] . rawurlencode( $file->name );
            foreach ( $this->options[ 'image_versions' ] as $version => $options ) {
                if ( is_file( $options[ 'upload_dir' ] . $file_name ) ) {
                    $file->{$version . '_url'} = $options[ 'upload_url' ]
                            . rawurlencode( $file->name );
                }
            }
            $this->set_file_delete_url( $file );

            return $file;
        }

        return null;
    }

    protected function get_file_objects() {
        return array_values( array_filter( array_map(
                array( $this, 'get_file_object' ), scandir( $this->options[ 'upload_dir' ] )
        ) ) );
    }

    protected function create_scaled_image( $file_name, $options ) {
        $file_path     = $this->options[ 'upload_dir' ] . $file_name;
        $new_file_path = $options[ 'upload_dir' ] . $file_name;
        list( $img_width, $img_height ) = @getimagesize( $file_path );
        if ( !$img_width || !$img_height ) {
            return false;
        }
        $scale = min(
                $options[ 'max_width' ] / $img_width, $options[ 'max_height' ] / $img_height
        );
        if ( $scale >= 1 ) {
            if ( $file_path !== $new_file_path ) {
                return copy( $file_path, $new_file_path );
            }

            return true;
        }
        $new_width  = $img_width * $scale;
        $new_height = $img_height * $scale;
        $new_img    = @imagecreatetruecolor( $new_width, $new_height );
        switch ( strtolower( substr( strrchr( $file_name, '.' ), 1 ) ) ) {
            case 'jpg':
            case 'jpeg':
                $src_img       = @imagecreatefromjpeg( $file_path );
                $write_image   = 'imagejpeg';
                $image_quality = isset( $options[ 'jpeg_quality' ] ) ?
                        $options[ 'jpeg_quality' ] : 75;
                break;
            case 'gif':
                @imagecolortransparent( $new_img, @imagecolorallocate( $new_img, 0, 0, 0 ) );
                $src_img       = @imagecreatefromgif( $file_path );
                $write_image   = 'imagegif';
                $image_quality = null;
                break;
            case 'png':
                @imagecolortransparent( $new_img, @imagecolorallocate( $new_img, 0, 0, 0 ) );
                @imagealphablending( $new_img, false );
                @imagesavealpha( $new_img, true );
                $src_img       = @imagecreatefrompng( $file_path );
                $write_image   = 'imagepng';
                $image_quality = isset( $options[ 'png_quality' ] ) ?
                        $options[ 'png_quality' ] : 9;
                break;
            default:
                $src_img = null;
        }
        $success = $src_img && @imagecopyresampled(
                        $new_img, $src_img, 0, 0, 0, 0, $new_width, $new_height, $img_width, $img_height
                ) && $write_image( $new_img, $new_file_path, $image_quality );
        // Free up memory (imagedestroy does not delete files):
        @imagedestroy( $src_img );
        @imagedestroy( $new_img );

        return $success;
    }

    protected function validate( $uploaded_file, $file, $error, $index ) {
        //TODO: these errors are shown in the UI but are not user friendly.

        $right_mime=false;
        if($file->type!==null){

            if ( !$this->_isRightMime( $file ) && (!isset($file->error) || empty($file->error) ) ) {
                $right_mime=false;
            }
            else{
                $right_mime=true;
            }
        }

        $out_filename = ZipArchiveExtended::getFileName( $file->name );
        if ( !$this->_isRightExtension( $file ) && ( !isset( $file->error ) || empty( $file->error ) ) && !$right_mime) {
            $file->error = "File Extension and Mime type Not Allowed";
            return false;

        }

        if ( $error ) {
            $file->error = $error;

            return false;
        }
        if ( !$file->name ) {
            $file->error = 'missingFileName';

            return false;
        }
        else if(mb_strlen($file->name) > INIT::$MAX_FILENAME_LENGTH){
            $file->error = "filenameTooLong";
            return false;
        }

        if ( !preg_match( $this->options[ 'accept_file_types' ], $file->name ) ) {
            $file->error = 'acceptFileTypes';

            return false;
        }
        if ( $uploaded_file && is_uploaded_file( $uploaded_file ) ) {
            $file_size = filesize( $uploaded_file );
        } else {
            $file_size = $_SERVER[ 'CONTENT_LENGTH' ];
        }
        if ( $this->options[ 'max_file_size' ] && (
                        $file_size > $this->options[ 'max_file_size' ] ||
                        $file->size > $this->options[ 'max_file_size' ] )
        ) {
            $file->error = 'maxFileSize';

            return false;
        }
        if ( $this->options[ 'min_file_size' ] &&
                $file_size < $this->options[ 'min_file_size' ]
        ) {
            $file->error = 'minFileSize';

            return false;
        }
        if ( is_int( $this->options[ 'max_number_of_files' ] ) && (
                        count( $this->get_file_objects() ) >= $this->options[ 'max_number_of_files' ] )
        ) {
            $file->error = 'maxNumberOfFiles';

            return false;
        }
        list( $img_width, $img_height ) = @getimagesize( $uploaded_file );
        if ( is_int( $img_width ) ) {
            if ( $this->options[ 'max_width' ] && $img_width > $this->options[ 'max_width' ] ||
                    $this->options[ 'max_height' ] && $img_height > $this->options[ 'max_height' ]
            ) {
                $file->error = 'maxResolution';

                return false;
            }
            if ( $this->options[ 'min_width' ] && $img_width < $this->options[ 'min_width' ] ||
                    $this->options[ 'min_height' ] && $img_height < $this->options[ 'min_height' ]
            ) {
                $file->error = 'minResolution';

                return false;
            }
        }

        return true;
    }

    protected function upcount_name_callback( $matches ) {
        $index = isset( $matches[ 1 ] ) ? intval( $matches[ 1 ] ) + 1 : 1;
        $ext   = isset( $matches[ 2 ] ) ? $matches[ 2 ] : '';

        return '_(' . $index . ')' . $ext;
    }

    protected function upcount_name( $name ) {
        return preg_replace_callback(
                '/(?:(?:_\(([\d]+)\))?(\.[^.]+))?$/', array( $this, 'upcount_name_callback' ), $name, 1
        );
    }

    private function my_basename( $param, $suffix = null ) {
        if ( $suffix ) {
            $tmpstr = ltrim( substr( $param, strrpos( $param, DIRSEP ) ), DIRSEP );
            if ( ( strpos( $param, $suffix ) + strlen( $suffix ) ) == strlen( $param ) ) {
                return str_ireplace( $suffix, '', $tmpstr );
            } else {
                return ltrim( substr( $param, strrpos( $param, DIRSEP ) ), DIRSEP );
            }
        } else {
            return ltrim( substr( $param, strrpos( $param, DIRSEP ) ), DIRSEP );
        }
    }

    /**
     * Remove path information and dots around the filename, to prevent uploading
     * into different directories or replacing hidden system files.
     * Also remove control characters and spaces (\x00..\x20) around the filename:
     */
    protected function trim_file_name( $name, $type, $index ) {
        $name = stripslashes( $name );
        //echo "name01 $name\n";
        $file_name = trim( $this->my_basename( $name ), ".\x00..\x20" );
        //echo "name1 $file_name\n";
        // Add missing file extension for known image types:
        if ( strpos( $file_name, '.' ) === false &&
                preg_match( '/^image\/(gif|jpe?g|png)/', $type, $matches )
        ) {
            $file_name .= '.' . $matches[ 1 ];
        }

        //remove spaces
        $file_name = str_replace( array( " ", " " ), "_", $file_name );

        if ( $this->options[ 'discard_aborted_uploads' ] ) {
            while ( is_file( $this->options[ 'upload_dir' ] . $file_name ) ) {
                $file_name = $this->upcount_name( $file_name );
            }
        }

        //echo "name3 $file_name\n";
        return $file_name;
    }

    protected function handle_form_data( $file, $index ) {
        // Handle form data, e.g. $_REQUEST['description'][$index]
    }

    protected function orient_image( $file_path ) {
        $exif = @exif_read_data( $file_path );
        if ( $exif === false ) {
            return false;
        }
        $orientation = intval( @$exif[ 'Orientation' ] );
        if ( !in_array( $orientation, array( 3, 6, 8 ) ) ) {
            return false;
        }
        $image = @imagecreatefromjpeg( $file_path );
        switch ( $orientation ) {
            case 3:
                $image = @imagerotate( $image, 180, 0 );
                break;
            case 6:
                $image = @imagerotate( $image, 270, 0 );
                break;
            case 8:
                $image = @imagerotate( $image, 90, 0 );
                break;
            default:
                return false;
        }
        $success = imagejpeg( $image, $file_path );
        // Free up memory (imagedestroy does not delete files):
        @imagedestroy( $image );

        return $success;
    }

    protected function handle_file_upload( $uploaded_file, $name, $size, $type, $error, $index = null ) {

        Log::$fileName = "upload.log";
        Log::doLog( $uploaded_file );

        $file       = new stdClass();
        $file->name = $this->trim_file_name( $name, $type, $index );
        $file->size = intval( $size );
        $file->type = $type;
        if ( $this->validate( $uploaded_file, $file, $error, $index ) ) {
            $this->handle_form_data( $file, $index );
            $file_path   = $this->options[ 'upload_dir' ] . $file->name;
            $append_file = !$this->options[ 'discard_aborted_uploads' ] &&
                    is_file( $file_path ) && $file->size > filesize( $file_path );
            clearstatcache();
            if ( $uploaded_file && is_uploaded_file( $uploaded_file ) ) {
                // multipart/formdata uploads (POST method uploads)
                if ( $append_file ) {
                    $res = file_put_contents(
                            $file_path, fopen( $uploaded_file, 'r' ), FILE_APPEND
                    );
                    Log::doLog( $res );
                } else {
                    $res = move_uploaded_file( $uploaded_file, $file_path );
                    Log::doLog( $res );
                }
            } else {
                // Non-multipart uploads (PUT method support)
                $res = file_put_contents(
                        $file_path, fopen( 'php://input', 'r' ), $append_file ? FILE_APPEND : 0
                );
                Log::doLog( $res );
            }

            clearstatcache();
            $file_size = filesize( $file_path );
            if ( $file_size === $file->size ) {
                if ( $this->options[ 'orient_image' ] ) {
                    $this->orient_image( $file_path );
                }
                $file->url = $this->options[ 'upload_url' ] . rawurlencode( $file->name );
                foreach ( $this->options[ 'image_versions' ] as $version => $options ) {
                    if ( $this->create_scaled_image( $file->name, $options ) ) {
                        if ( $this->options[ 'upload_dir' ] !== $options[ 'upload_dir' ] ) {
                            $file->{$version . '_url'} = $options[ 'upload_url' ]
                                    . rawurlencode( $file->name );
                        } else {
                            clearstatcache();
                            $file_size = filesize( $file_path );
                        }
                    }
                }
            } else if ( $this->options[ 'discard_aborted_uploads' ] ) {
                unlink( $file_path );
                $file->error = 'abort';
            }
            $file->size = $file_size;
            $this->set_file_delete_url( $file );

            Log::doLog( "Size on disk: $file_size - Declared size: $file->size" );

            $fileContent    = file_get_contents( $file_path );
            $preg_file_html = '|<file original="(.*?)" source-language="(.*?)" datatype="(.*?)" target-language="(.*?)">|m';
            $res            = array();
            preg_match_all( $preg_file_html, $fileContent, $res, PREG_SET_ORDER );
            if ( !empty( $res ) ) {
                $file->internal_source_lang = addslashes( $res[ 0 ][ 2 ] );
                $file->internal_target_lang = addslashes( $res[ 0 ][ 4 ] );
            }

            //As opposed with isset(), property_exists() returns TRUE even if the property has the value NULL.
            if ( property_exists( $file, 'error' ) ) {
                // FORMAT ERROR MESSAGE
                switch ( $file->error ) {
                    case 'abort':
                        $file->error = "File upload failed. Refresh the page using CTRL+R (or CMD+R) and try again.";
                        break;
                    default:
                        null;
                }
            }

        }

        /**
         *
         * OLD
         * Conversion check are now made server side
         */
        $file->convert = true;

        return $file;
    }

    public function get() {
        $file_name = isset( $_REQUEST[ 'file' ] ) ?
                basename( stripslashes( $_REQUEST[ 'file' ] ) ) : null;
        if ( $file_name ) {
            $info = $this->get_file_object( $file_name );
        } else {
            $info = $this->get_file_objects();
        }
        header( 'Content-type: application/json' );
        echo json_encode( $info );
    }

    public function post() {
        if ( isset( $_REQUEST[ '_method' ] ) && $_REQUEST[ '_method' ] === 'DELETE' ) {
            return $this->delete();
        }

        $upload = isset( $_FILES[ $this->options[ 'param_name' ] ] ) ?
                $_FILES[ $this->options[ 'param_name' ] ] : null;
        $info   = array();
        if ( $upload && is_array( $upload[ 'tmp_name' ] ) ) {
            // param_name is an array identifier like "files[]",
            // $_FILES is a multi-dimensional array:
            foreach ( $upload[ 'tmp_name' ] as $index => $value ) {
                $info[] = $this->handle_file_upload(
                        $upload[ 'tmp_name' ][ $index ], isset( $_SERVER[ 'HTTP_X_FILE_NAME' ] ) ?
                        $_SERVER[ 'HTTP_X_FILE_NAME' ] : $upload[ 'name' ][ $index ], isset( $_SERVER[ 'HTTP_X_FILE_SIZE' ] ) ?
                        $_SERVER[ 'HTTP_X_FILE_SIZE' ] : $upload[ 'size' ][ $index ], isset( $_SERVER[ 'HTTP_X_FILE_TYPE' ] ) ?
                        $_SERVER[ 'HTTP_X_FILE_TYPE' ] : $upload[ 'type' ][ $index ], $upload[ 'error' ][ $index ], $index
                );
            }
        } elseif ( $upload || isset( $_SERVER[ 'HTTP_X_FILE_NAME' ] ) ) {
            // param_name is a single object identifier like "file",
            // $_FILES is a one-dimensional array:
            $info[] = $this->handle_file_upload(
                    isset( $upload[ 'tmp_name' ] ) ? $upload[ 'tmp_name' ] : null, isset( $_SERVER[ 'HTTP_X_FILE_NAME' ] ) ?
                    $_SERVER[ 'HTTP_X_FILE_NAME' ] : ( isset( $upload[ 'name' ] ) ?
                            $upload[ 'name' ] : null ), isset( $_SERVER[ 'HTTP_X_FILE_SIZE' ] ) ?
                    $_SERVER[ 'HTTP_X_FILE_SIZE' ] : ( isset( $upload[ 'size' ] ) ?
                            $upload[ 'size' ] : null ), isset( $_SERVER[ 'HTTP_X_FILE_TYPE' ] ) ?
                    $_SERVER[ 'HTTP_X_FILE_TYPE' ] : ( isset( $upload[ 'type' ] ) ?
                            $upload[ 'type' ] : null ), isset( $upload[ 'error' ] ) ? $upload[ 'error' ] : null
            );
        }

        //check for server misconfiguration
        $uploadParams = ServerCheck::getInstance()->getUploadParams();
        if ( $_SERVER[ 'CONTENT_LENGTH' ] >= $uploadParams->getPostMaxSize() ) {

            $fp = fopen( "php://input", "r" );

            list( $trash, $boundary ) = explode( 'boundary=', $_SERVER[ 'CONTENT_TYPE' ] );

            $regexp = '/' . $boundary . '.*?filename="(.*)".*?Content-Type:(.*)\x{0D}\x{0A}\x{0D}\x{0A}/sm';

            $readBuff = fread( $fp, 1024 );
            while ( !preg_match( $regexp, $readBuff, $matches ) ) {
                $readBuff .= fread( $fp, 1024 );
            }
            fclose( $fp );

            $file = new stdClass();
            $file->name = $this->trim_file_name( $matches[1], trim( $matches[2] ), null );
            $file->size = null;
            $file->type = trim( $matches[2] );
            $file->error = "The file is too large. ".
                           "Please Contact " . INIT::$SUPPORT_MAIL . " and report these details: ".
                           "\"The server configuration does not conform with Matecat configuration. ".
                           "Check for max header post size value in the virtualhost configuration or php.ini.\"";

            $info = array( $file );

        } elseif( $_SERVER['CONTENT_LENGTH'] >= $uploadParams->getUploadMaxFilesize() ){
            $info[0]->error = "The file is too large.  ".
                              "Please Contact " . INIT::$SUPPORT_MAIL . " and report these details: ".
                              "\"The server configuration does not conform with Matecat configuration. ".
                              "Check for max file upload value in the virtualhost configuration or php.ini.\"";
        }
        //check for server misconfiguration

        header( 'Vary: Accept' );
        $json     = json_encode( $info );
        $redirect = isset( $_REQUEST[ 'redirect' ] ) ?
                stripslashes( $_REQUEST[ 'redirect' ] ) : null;
        if ( $redirect ) {
            header( 'Location: ' . sprintf( $redirect, rawurlencode( $json ) ) );

            return;
        }
        if ( isset( $_SERVER[ 'HTTP_ACCEPT' ] ) &&
                ( strpos( $_SERVER[ 'HTTP_ACCEPT' ], 'application/json' ) !== false )
        ) {
            header( 'Content-type: application/json' );
        } else {
            header( 'Content-type: text/plain' );
        }
        echo $json;
    }

    public function delete() {

        /*
         * BUG FIXED: UTF16 / UTF8 File name conversion related
         *
         * $file_name = isset($_REQUEST['file']) ? basename(stripslashes($_REQUEST['file'])) : null;
         *
         * ----> basename is NOT UTF8 compliant
         */
        $file_name = null;
        if ( isset( $_REQUEST[ 'file' ] ) ) {

            $raw_file  = explode( DIRECTORY_SEPARATOR, $_REQUEST[ 'file' ] );
            $file_name = array_pop( $raw_file );

        }

        $file_info = FilesStorage::pathinfo_fix( $file_name );

        //if it's a zip file, delete it and all its contained files.
        if ( $file_info[ 'extension' ] == 'zip' ) {
            $success = $this->zipFileDelete( $file_name );
        } //if it's a file in a zipped folder, delete it.
        elseif ( preg_match( '#^[^\.]*\.zip/#', $_REQUEST[ 'file' ] ) ) {
            $file_name = ZipArchiveExtended::getInternalFileName($_REQUEST[ 'file' ]);

            $success = $this->zipInternalFileDelete( $file_name );
        } else {
            $success = $this->normalFileDelete( $file_name );
        }

        header( 'Content-type: application/json' );
        echo json_encode( $success );

    }

    private function normalFileDelete( $file_name ) {

        $file_path = $this->options[ 'upload_dir' ] . $file_name;

        $this->deleteSha( $file_path );

        $success[ $file_name ] = is_file( $file_path ) && $file_name[ 0 ] !== '.' && unlink( $file_path );
        if ( $success[ $file_name ] ) {
            $this->deleteThumbnails( $file_name );
        }

        return $success;
    }

    private function zipFileDelete( $file_name ) {
        $file_path = $this->options[ 'upload_dir' ] . $file_name;

        $out_file_name = ZipArchiveExtended::getFileName( $file_name );

        $success[ $out_file_name ] = is_file( $file_path ) && $file_name[ 0 ] !== '.' && unlink( $file_path );
        if ( $success[ $out_file_name ] ) {

            $this->deleteThumbnails( $file_name );

            $containedFiles = glob( $this->options[ 'upload_dir' ] . $file_name . "*" );
            $k              = 0;

            while ( $k < count( $containedFiles ) ) {
                $internalFileName = str_replace( $this->options[ 'upload_dir' ], "", $containedFiles[ $k ] );
                $success          = array_merge( $success, $this->zipInternalFileDelete( $internalFileName ) );
                $k++;
            }

        }

        return $success;
    }

    private function zipInternalFileDelete( $file_name ) {
        $file_path = $this->options[ 'upload_dir' ] . $file_name;
        $this->deleteSha( $file_path );

        $out_file_name = ZipArchiveExtended::getFileName( $file_name );

        $success[ $out_file_name ] = is_file( $file_path ) && $file_name[ 0 ] !== '.' && unlink( $file_path );
        if ( $success[ $out_file_name ] ) {
            $this->deleteThumbnails( $file_name );
        }

        return $success;
    }

    /**
     * Avoid race conditions by javascript multiple calls
     *
     * @param $file_path
     */
    private function deleteSha( $file_path ) {

        $sha1 = sha1_file( $file_path );
        if( !$sha1 ) return;

        //can be present more than one file with the same sha
        //so in the sha1 file there could be more than one row
        $file_sha = glob( $this->options[ 'upload_dir' ] . $sha1 . "*" ); //delete sha1 also

        $fp = fopen( $file_sha[0], "r+");

        // no file found
        if( !$fp ) return;

        $i = 0;
        while ( !flock( $fp, LOCK_EX | LOCK_NB ) ) {  // acquire an exclusive lock
            $i++;
            if( $i == 40 ) return; //exit the loop after 2 seconds, can not acquire the lock
            usleep( 50000 );
            continue;
        }

        $file_content = fread( $fp, filesize( $file_sha[0] ) );
        $file_content_array = explode( "\n", $file_content );

        //remove the last line ( is an empty string )
        array_pop( $file_content_array );

        $fileName = FilesStorage::basename_fix( $file_path );

        $key = array_search( $fileName, $file_content_array );
        unset( $file_content_array[ $key ] );

        if ( !empty( $file_content_array ) ) {
            fseek( $fp, 0 ); //rewind
            ftruncate( $fp, 0 ); //truncate to zero bytes length
            fwrite( $fp, implode( "\n", $file_content_array ) . "\n" );
            fflush( $fp );
            flock( $fp, LOCK_UN );    // release the lock
            fclose( $fp );
        } else {
            flock( $fp, LOCK_UN );    // release the lock
            fclose( $fp );
            @unlink( @$file_sha[ 0 ] );
        }

    }

    private function deleteThumbnails( $file_name ) {
        foreach ( $this->options[ 'image_versions' ] as $version => $options ) {
            $file = $options[ 'upload_dir' ] . $file_name;
            if ( is_file( $file ) ) {
                unlink( $file );
            }
        }
    }

    protected function _isRightMime( $fileUp ) {

        //if empty accept ALL File Types
        if ( empty ( $this->acceptedMime ) ) {
            return true;
        }

        foreach ( $this->acceptedMime as $this_mime ) {
            if ( strpos( $fileUp->type, $this_mime ) !== false ) {
                return true;
            }
        }

        return false;
    }

    protected function _isRightExtension( $fileUp ) {

        $fileNameChunks = explode( ".", $fileUp->name );

        foreach ( INIT::$SUPPORTED_FILE_TYPES as $key => $value ) {
            foreach ( $value as $typeSupported => $value2 ) {
                if ( preg_match( '/\.' . $typeSupported . '$/i', $fileUp->type ) ) {
                    return true;
                }
            }
        }
        //first Check the extension
        if ( !array_key_exists( strtolower( $fileNameChunks[ count( $fileNameChunks ) - 1 ] ), $this->acceptedExtensions ) ) {
            return false;
        }

        return true;
    }

}
