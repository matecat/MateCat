<?php

use Model\Conversion\MimeTypes\MimeTypes;
use Model\Conversion\ZipArchiveHandler;
use Model\FilesStorage\AbstractFilesStorage;
use Utils\Logger\LoggerFactory;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\ServerCheck\ServerCheck;
use Utils\Tools\CatUtils;
use Utils\Tools\Utils;


class UploadHandler
{

    const string DIRSEP = "//";

    /**
     * @var array
     */
    protected array $options;

    protected MatecatLogger $logger;

    function __construct()
    {
        $this->logger = LoggerFactory::getLogger("upload_handler");

        $this->options = [
                'script_url'              => $this->getFullUrl() . '/',
                'upload_token'            => $_COOKIE[ 'upload_token' ],
                'upload_dir'              => Utils::uploadDirFromSessionCookie($_COOKIE[ 'upload_token' ]),
                'upload_url'              => $this->getFullUrl() . '/files/',
                'param_name'              => 'files',
            // Set the following option to 'POST', if your server does not support
            // DELETE requests. This is a parameter sent to the client:
                'delete_type'             => 'DELETE',
            // The php.ini settings upload_max_filesize and post_max_size
            // take precedence over the following max_file_size setting:
                'max_tmx_file_size'       => AppConfig::$MAX_UPLOAD_TMX_FILE_SIZE,
                'max_file_size'           => AppConfig::$MAX_UPLOAD_FILE_SIZE,
                'min_file_size'           => 1,
            // The maximum number of files for the upload directory:
                'max_number_of_files'     => AppConfig::$MAX_NUM_FILES,
            // Set the following option to false, enable resumable uploads:
                'discard_aborted_uploads' => true,
        ];
    }

    protected function getFullUrl(): string
    {
        $https = AppConfig::$PROTOCOL === 'https';

        /** @noinspection HttpUrlsUsage */
        return
                ($https ? 'https://' : 'http://') .
                (!empty($_SERVER[ 'REMOTE_USER' ]) ? $_SERVER[ 'REMOTE_USER' ] . '@' : '') .
                ($_SERVER[ 'HTTP_HOST' ] ?? (
                        ($_SERVER[ 'SERVER_NAME' ] ?? '') .
                        ($https && ($_SERVER[ 'SERVER_PORT' ] ?? 0) === 443 || ($_SERVER[ 'SERVER_PORT' ] ?? 0) === 80 ? '' : ':' . $_SERVER[ 'SERVER_PORT' ]))) .
                rtrim($_SERVER[ 'REQUEST_URI' ] ?? '', '/');
    }

    protected function set_file_delete_url($file): void
    {
        $file->delete_url  = $this->options[ 'script_url' ]
                . '?file=' . rawurlencode($file->name);
        $file->delete_type = $this->options[ 'delete_type' ];
        if ($file->delete_type !== 'DELETE') {
            $file->delete_url .= '&_method=DELETE';
        }
    }

    protected function get_file_object($file_name): ?stdClass
    {
        $file_path = $this->options[ 'upload_dir' ] . $file_name;
        if (is_file($file_path) && $file_name[ 0 ] !== '.') {
            $file       = new stdClass();
            $file->name = $file_name;
            $file->size = filesize($file_path);
            $file->url  = $this->options[ 'upload_url' ] . rawurlencode($file->name);
            $this->set_file_delete_url($file);

            return $file;
        }

        return null;
    }

    protected function get_file_objects(): array
    {
        return array_values(
                array_filter(
                        array_map(
                                [$this, 'get_file_object'],
                                scandir($this->options[ 'upload_dir' ])
                        )
                )
        );
    }

    /**
     * @param string $fileName
     *
     * @throws Exception
     */
    protected static function _validateFileName(string $fileName): void
    {
        if (!Utils::isValidFileName($fileName)) {
            throw new Exception("Invalid File Name");
        }
    }

    /**
     * @param string $token
     *
     * @throws Exception
     */
    protected static function _validateToken(string $token): void
    {
        if (!Utils::isTokenValid($token)) {
            throw new Exception("Invalid Upload Token.");
        }
    }

    protected function validate(string $uploaded_file, stdClass $file, string $error): bool
    {
        if ($error) {
            $file->error = $error;

            return false;
        }

        try {
            self::_validateFileName($file->name);
            self::_validateToken($this->options[ 'upload_token' ]);
        } catch (Exception $e) {
            $file->error = $e->getMessage();

            return false;
        }

        if ($uploaded_file && is_uploaded_file($uploaded_file)) {
            $file_size = filesize($uploaded_file);
        } else {
            $file_size = $_SERVER[ 'CONTENT_LENGTH' ];
        }

        // check if is a TMX
        // for TMX the limit is different (300Mb vs 100Mb)
        $file_pathinfo = pathinfo($file->name);
        $max_file_size = ($file_pathinfo[ 'extension' ] === 'tmx') ? $this->options[ 'max_tmx_file_size' ] : $this->options[ 'max_file_size' ];

        if ($max_file_size && (
                        $file_size > $max_file_size ||
                        $file->size > $max_file_size)
        ) {
            $file->error = 'maxFileSize';

            return false;
        }

        if ($this->options[ 'min_file_size' ] &&
                $file_size < $this->options[ 'min_file_size' ]
        ) {
            $file->error = 'minFileSize';

            return false;
        }

        if (is_int($this->options[ 'max_number_of_files' ]) && (
                        count($this->get_file_objects()) >= $this->options[ 'max_number_of_files' ])
        ) {
            $file->error = 'maxNumberOfFiles';

            return false;
        }

        if ($file->type !== null) {
            if (!$this->_isRightMime($file) && (empty($file->error))) {
                $file->error = "File format not supported";

                return false;
            }
        }

        if (!$this->_isRightExtension($file) && (empty($file->error))) {
            $file->error = "File Extension Not Allowed";

            return false;
        }


        if (!$file->name) {
            $file->error = 'missingFileName';

            return false;
        } elseif (mb_strlen($file->name) > AppConfig::$MAX_FILENAME_LENGTH) {
            $file->error = "filenameTooLong";

            return false;
        }

        return true;
    }

    protected function up_count_name_callback($matches): string
    {
        $index = isset($matches[ 1 ]) ? intval($matches[ 1 ]) + 1 : 1;
        $ext   = $matches[ 2 ] ?? '';

        return '_(' . $index . ')' . $ext;
    }

    protected function up_count_name(string $name): string
    {
        return preg_replace_callback(
                '/(?:(?:_\((\d+)\))?(\.[^.]+))?$/',
                [$this, 'up_count_name_callback'],
                $name,
                1
        );
    }

    private function my_basename(string $param, ?string $suffix = null): string
    {
        if ($suffix) {
            $tmp_str = ltrim(substr($param, strrpos($param, self::DIRSEP)), self::DIRSEP);
            if ((strpos($param, $suffix) + strlen($suffix)) == strlen($param)) {
                return str_ireplace($suffix, '', $tmp_str);
            } else {
                return ltrim(substr($param, strrpos($param, self::DIRSEP)), self::DIRSEP);
            }
        } else {
            return ltrim(substr($param, strrpos($param, self::DIRSEP)), self::DIRSEP);
        }
    }

    /**
     * Remove path information and dots around the filename, to prevent uploading
     * into different directories or replacing hidden system files.
     * Also remove control characters and spaces (\x00..\x20) around the filename:
     */
    protected function trim_file_name(string $name): string
    {
        $name = stripslashes($name);

        $file_name = trim($this->my_basename($name), ".\x00..\x20");

        if ($this->options[ 'discard_aborted_uploads' ]) {
            while (is_file($this->options[ 'upload_dir' ] . $file_name)) {
                $file_name = $this->up_count_name($file_name);
            }
        }

        return $file_name;
    }

    protected function handle_file_upload(string $uploaded_file, string $name, int $size, string $error): stdClass
    {
        $this->logger->debug($uploaded_file);

        $file           = new stdClass();
        $file->name     = $this->trim_file_name($name);
        $file->size     = $size;
        $file->tmp_name = $uploaded_file;

        $file->type = $this->getMimeContentType($file->tmp_name);

        if (false === $file->type) {
            $file->error = "Mime type was not recognized";
        }

        if ($this->validate($uploaded_file, $file, $error)) {
            $file_path   = $this->options[ 'upload_dir' ] . $file->name;
            $append_file = !$this->options[ 'discard_aborted_uploads' ] &&
                    is_file($file_path) && $file->size > filesize($file_path);
            clearstatcache();
            if ($uploaded_file && is_uploaded_file($uploaded_file)) {
                // multipart/formdata uploads (POST method uploads)
                if ($append_file) {
                    $res = file_put_contents(
                            $file_path,
                            fopen($uploaded_file, 'r'),
                            FILE_APPEND
                    );
                } else {
                    $res = move_uploaded_file($uploaded_file, $file_path);
                }
            } else {
                // Non-multipart uploads (PUT method support)
                $res = file_put_contents(
                        $file_path,
                        fopen('php://input', 'r'),
                        $append_file ? FILE_APPEND : 0
                );
            }
            $this->logger->debug($res);

            clearstatcache();
            $file_size = filesize($file_path);
            if ($file_size === $file->size) {
                $file->url = $this->options[ 'upload_url' ] . rawurlencode($file->name);
            } elseif ($this->options[ 'discard_aborted_uploads' ]) {
                unlink($file_path);
                $file->error = 'abort';
            }
            $file->size = $file_size;
            $this->set_file_delete_url($file);

            $this->logger->debug("Size on disk: $file_size - Declared size: $file->size");

            //As opposed with isset(), property_exists() returns TRUE even if the property has the value NULL.
            if (property_exists($file, 'error')) {
                // FORMAT ERROR MESSAGE
                switch ($file->error) {
                    case 'abort':
                        $file->error = "File upload failed. Refresh the page using CTRL+R (or CMD+R) and try again.";
                        break;
                    default:
                        break;
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

    /**
     * Detection of MIME Type improvement
     *
     * Using File Information extention asa backup method
     * https://www.php.net/manual/en/fileinfo.installation.php
     *
     * File Information seems to be slightly better than mime_content_type function
     * because tries to guess the content type and encoding of a file by looking for certain magic byte sequences at specific positions within the file.
     * While this is not a bullet proof approach the heuristics used do a very good job.
     *
     * Even though some false positive are always possible. Take a look here:
     *
     * https://stackoverflow.com/questions/16190929/detecting-a-mime-type-fails-in-php
     *
     * Returns false in case of failure
     *
     * @param string $filename
     *
     * @return string|bool
     */
    private function getMimeContentType(string $filename): bool|string
    {
        if (function_exists('mime_content_type')) {
            return (new MimeTypes())->guessMimeType($filename);
        }

        if (function_exists('finfo_open')) {
            $finfo     = finfo_open(FILEINFO_MIME_TYPE);
            $finfoFile = finfo_file($finfo, $filename);
            finfo_close($finfo);

            return $finfoFile;
        }

        return 'application/octet-stream';
    }

    public function get(): void
    {
        $file_name = isset($_REQUEST[ 'file' ]) ?
                basename(stripslashes($_REQUEST[ 'file' ])) : null;
        if ($file_name) {
            $info = $this->get_file_object($file_name);
        } else {
            $info = $this->get_file_objects();
        }
        header('Content-type: application/json');
        echo json_encode($info);
    }

    /**
     * @throws ReflectionException
     */
    public function post(): void
    {
        if (isset($_REQUEST[ '_method' ]) && $_REQUEST[ '_method' ] === 'DELETE') {
            $this->delete();

            return;
        }

        if (!Utils::isTokenValid($_COOKIE[ 'upload_token' ])) {
            $info             = [new stdClass()];
            $info[ 0 ]->error = "Invalid Upload Token. Check your browser, cookies must be enabled for this domain.";
            $this->flush($info);
        }

        $upload = $_FILES[ $this->options[ 'param_name' ] ] ?? null;

        $info = [];
        if ($upload && is_array($upload[ 'tmp_name' ])) {
            // param_name is an array identifier like "files[]",
            // $_FILES is a multi-dimensional array:
            foreach ($upload[ 'tmp_name' ] as $index => $value) {
                $info[] = $this->handle_file_upload(
                        $upload[ 'tmp_name' ][ $index ],
                        $upload[ 'name' ][ $index ],
                        $upload[ 'size' ][ $index ],
                        $upload[ 'error' ][ $index ]
                );
            }
        } elseif ($upload || isset($_SERVER[ 'HTTP_X_FILE_NAME' ])) {
            // param_name is a single object identifier like "file",
            // $_FILES is a one-dimensional array:
            $info[] = $this->handle_file_upload(
                    $upload[ 'tmp_name' ] ?? null,
                    $upload[ 'name' ] ?? null,
                    $upload[ 'size' ] ?? null,
                    $upload[ 'error' ] ?? null
            );
        }

        //check for server misconfiguration
        $uploadParams = ServerCheck::getInstance()->getUploadParams();

        if ($_SERVER[ 'CONTENT_LENGTH' ] >= $uploadParams->getPostMaxSize()) {
            $fp = fopen("php://input", "r");

            [, $boundary] = explode('boundary=', $_SERVER[ 'CONTENT_TYPE' ]);

            $regexp = '/' . $boundary . '.*?filename="(.*)".*?Content-Type:(.*)\x{0D}\x{0A}\x{0D}\x{0A}/sm';

            $readBuff = fread($fp, 1024);
            while (!preg_match($regexp, $readBuff, $matches)) {
                $readBuff .= fread($fp, 1024);
            }
            fclose($fp);

            $file        = new stdClass();
            $file->name  = $this->trim_file_name($matches[ 1 ]);
            $file->size  = null;
            $file->type  = trim($matches[ 2 ]);
            $file->error = "The file is too large. " .
                    "Please Contact " . AppConfig::$SUPPORT_MAIL . " and report these details: " .
                    "\"The server configuration does not conform with Matecat configuration. " .
                    "Check for max header post size value in the virtualhost configuration or php.ini.\"";

            $info = [$file];
        } elseif ($_SERVER[ 'CONTENT_LENGTH' ] >= $uploadParams->getUploadMaxFilesize()) {
            $info[ 0 ]->error = "The file is too large.  " .
                    "Please Contact " . AppConfig::$SUPPORT_MAIL . " and report these details: " .
                    "\"The server configuration does not conform with Matecat configuration. " .
                    "Check for max file upload value in the virtualhost configuration or php.ini.\"";
        }
        //check for server misconfiguration

        $this->flush($info);
    }

    public function flush(mixed $info): void
    {
        $json     = json_encode($info);
        $redirect = isset($_REQUEST[ 'redirect' ]) ? stripslashes($_REQUEST[ 'redirect' ]) : null;

        header('Vary: Accept');
        header('Content-type: application/json');

        if ($redirect) {
            header('Location: ' . sprintf($redirect, rawurlencode($json)));

            return;
        }

        echo $json;

        die();
    }

    /**
     * @throws ReflectionException
     */
    public function delete(): void
    {
        /*
         * BUG FIXED: UTF16 / UTF8 File name conversion related
         *
         * $file_name = isset($_REQUEST['file']) ? basename(stripslashes($_REQUEST['file'])) : null;
         *
         * ----> basename is NOT UTF8 compliant
         */
        $file_name = null;
        if (isset($_REQUEST[ 'file' ])) {
            $raw_file  = explode(DIRECTORY_SEPARATOR, $_REQUEST[ 'file' ]);
            $file_name = array_pop($raw_file);
        }

        try {
            self::_validateFileName($file_name);
            self::_validateToken($this->options[ 'upload_token' ]);
        } catch (Exception $e) {
            header('Content-type: application/json');
            echo json_encode(["code" => -1, "error" => $e->getMessage()]);

            return;
        }

        $file_info        = AbstractFilesStorage::pathinfo_fix($file_name);
        $source           = $_REQUEST[ 'source' ];
        $segmentationRule = $_REQUEST[ 'segmentationRule' ];
        $filtersTemplate  = $_REQUEST[ 'filtersTemplate' ];

        //if it's a zip file, delete it and all its contained files.
        if ($file_info[ 'extension' ] == 'zip') {
            $success = $this->zipFileDelete($file_name, $source, $segmentationRule, $filtersTemplate);
        } //if it's a file in a zipped folder, delete it.
        elseif (preg_match('#^[^\.]*\.zip/#', $_REQUEST[ 'file' ])) {
            $file_name = ZipArchiveHandler::getInternalFileName($_REQUEST[ 'file' ]);

            $success = $this->zipInternalFileDelete($file_name, $source, $segmentationRule, $filtersTemplate);
        } else {
            $success = $this->normalFileDelete($file_name, $source, $segmentationRule, $filtersTemplate);
        }

        header('Content-type: application/json');
        echo json_encode($success);
    }

    /**
     * @throws ReflectionException
     */
    private function normalFileDelete($file_name, $source, $segmentationRule = null, ?int $filtersTemplate = 0): array
    {
        $file_path = $this->options[ 'upload_dir' ] . $file_name;

        CatUtils::deleteSha($file_path, $source, $segmentationRule, $filtersTemplate);

        $success[ $file_name ] = is_file($file_path) && $file_name[ 0 ] !== '.' && unlink($file_path);

        return $success;
    }

    /**
     * @param     $file_name
     * @param     $source
     * @param     $segmentationRule
     * @param int $filtersTemplate
     *
     * @return array
     * @throws ReflectionException
     */
    private function zipFileDelete($file_name, $source, $segmentationRule = null, int $filtersTemplate = 0): array
    {
        $file_path = $this->options[ 'upload_dir' ] . $file_name;

        $out_file_name = ZipArchiveHandler::getFileName($file_name);

        $success[ $out_file_name ] = is_file($file_path) && $file_name[ 0 ] !== '.' && unlink($file_path);
        if ($success[ $out_file_name ]) {
            $containedFiles = glob($this->options[ 'upload_dir' ] . $file_name . "*");
            $k              = 0;

            while ($k < count($containedFiles)) {
                $internalFileName = str_replace($this->options[ 'upload_dir' ], "", $containedFiles[ $k ]);
                $success          = array_merge($success, $this->zipInternalFileDelete($internalFileName, $source, $segmentationRule, $filtersTemplate));
                $k++;
            }
        }

        return $success;
    }

    /**
     * @throws ReflectionException
     */
    private function zipInternalFileDelete($file_name, $source, $segmentationRule = null, ?int $filtersTemplate = 0): array
    {
        $file_path = $this->options[ 'upload_dir' ] . $file_name;
        CatUtils::deleteSha($file_path, $source, $segmentationRule, $filtersTemplate);

        $out_file_name = ZipArchiveHandler::getFileName($file_name);

        $success[ $out_file_name ] = is_file($file_path) && $file_name[ 0 ] !== '.' && unlink($file_path);

        return $success;
    }

    protected function _isRightMime($fileUp): bool
    {
        //Mime Allowlist, take them from ProjectManager.php
        foreach (AppConfig::$MIME_TYPES as $key => $value) {
            if (str_contains($key, $fileUp->type)) {
                return true;
            }
        }

        return false;
    }

    protected function _isRightExtension($fileUp): bool
    {
        $acceptedExtensions = [];
        foreach (AppConfig::$SUPPORTED_FILE_TYPES as $value2) {
            $acceptedExtensions = array_unique(array_merge($acceptedExtensions, array_keys($value2)));
        }

        $fileNameChunks = explode(".", $fileUp->name);

        //first Check the extension
        if (in_array(strtolower($fileNameChunks[ count($fileNameChunks) - 1 ]), $acceptedExtensions)) {
            return true;
        }

        return false;
    }
}
