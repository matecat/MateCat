<?php

// TODO: files should remain on disk, and not copied and transformed in memory several times
class Filters {

    const SOURCE_TO_XLIFF_ENDPOINT = "/AutomationService/original2xliff";
    const XLIFF_TO_TARGET_ENDPOINT = "/AutomationService/xliff2original";

    /**
     * @param $dataGroups array each value must be an associative array
     *                          representing the fields of a POST request
     * @param $endpoint string use one of the two constants of this class
     * @return array
     */
    private static function sendToFilters($dataGroups, $endpoint ) {
        $multiCurl = new MultiCurlHandler();

        // Each group is a POST request
        foreach ($dataGroups as $id => $data) {
            // Add to POST fields the version forced using the config file
            if ( $endpoint === self::SOURCE_TO_XLIFF_ENDPOINT
                    && ! empty( INIT::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION ) ) {
                $data['forceVersion'] = INIT::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION;
            }

            // Setup CURL options and add to MultiCURL
            $options = array (
                CURLOPT_POST => true,
                CURLOPT_USERAGENT => INIT::$FILTERS_USER_AGENT,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => $data,
                // Useful to debug the endpoint on the other end
                //CURLOPT_COOKIE => 'XDEBUG_SESSION=PHPSTORM'
            );
            if (! empty( INIT::$FILTERS_MASHAPE_KEY ) ) {
                $options[CURLOPT_HTTPHEADER] = array(
                  'X-Mashape-Key: '. INIT::$FILTERS_MASHAPE_KEY,
                );
            }

            if ( PHP_MINOR_VERSION >= 5 ) {
                /**
                 * Added in PHP 5.5.0 with FALSE as the default value.
                 * PHP 5.6.0 changes the default value to TRUE.
                 */
                $options[ CURLOPT_SAFE_UPLOAD ] = false;
            }

            $url = rtrim(INIT::$FILTERS_ADDRESS, '/') . $endpoint;
            Log::doLog( "Calling: " . $url );
            $multiCurl->createResource( $url, $options, $id );
            $multiCurl->setRequestHeader( $id );
        }

        // Launch the multiCURL and get all the results
        $multiCurl->multiExec();
        $responses = $multiCurl->getAllContents();
        $infos = $multiCurl->getAllInfo();
        $headers = $multiCurl->getAllHeaders();

        // Compute results
        foreach ($responses as $id => &$response) {
            $info = $infos[$id];

            // Compute response
            if ($info['http_code'] != 200 || $response === false) {
                $errResponse = array( "isSuccess" => false, "curlInfo" => $info );
                if ($response === '{"message":"Invalid Mashape Key"}') {
                    $errResponse['errorMessage'] = "Failed Mashape authentication. Check FILTERS_MASHAPE_KEY in config.ini";
                } else if ($info['errno']) {
                    $errResponse['errorMessage'] = "Curl error $info[errno]: $info[error]";
                } else {
                    $errResponse['errorMessage'] = "Received status code $info[http_code]";
                }
                $response = $errResponse;

            } else {
                $response = json_decode($response, true);
                // Super ugly, but this is the way the old FileFormatConverter
                // cooperated with callers; changing this requires changing the
                // callers, a huge and risky refactoring work I couldn't afford
                if ( isset( $response[ "documentContent" ] ) ) {
                    $response[ 'document_content' ] = base64_decode( $response[ 'documentContent' ] );
                    unset( $response[ 'documentContent' ] );
                }
            }

            // Compute headers
            $instanceInfo = self::extractInstanceInfoFromHeaders($headers[$id]);
            if ($instanceInfo !== false) {
                $response = array_merge($response, $instanceInfo);
            }

            // Add to response the CURL total time in milliseconds
            $response['time'] = round($info['curlinfo_total_time'] * 1000);
        }

        return $responses;
    }

    /**
     * Looks for the Filters-Instance header and returns the information in it.
     * @param $headers
     * @return array|bool an array with the address and version of the
     *                    respondent instance; false if the header was not found
     */
    private static function extractInstanceInfoFromHeaders( $headers ) {
        foreach ($headers as $header) {
            if (preg_match("|^Filters-Instance: address=([^;]+); version=(.+)$|", $header, $matches)) {
                return array(
                    'instanceAddress' => $matches[1],
                    'instanceVersion' => $matches[2]
                );
            }
        }
        return false;
    }

    public static function sourceToXliff( $filePath, $sourceLang, $targetLang, $segmentation ) {
        $basename  = FilesStorage::pathinfo_fix( $filePath, PATHINFO_FILENAME );
        $extension = FilesStorage::pathinfo_fix( $filePath, PATHINFO_EXTENSION );
        $filename = "$basename.$extension";

        $data = array(
            'documentContent' => "@$filePath",
            'sourceLocale' => Langs_Languages::getInstance()->getLangRegionCode( $sourceLang ),
            'targetLocale' => Langs_Languages::getInstance()->getLangRegionCode( $targetLang ),
            'segmentation' => $segmentation,
            // This parameter is mandatory for older filters and optional for
            // newer ones, because it can be obtained by documentContent;
            // however when new filters extract filename from documentContent
            // they do it forcing ISO-8859-1 encoding, because of a bug in the
            // MIMEPull library. So unless this bug is there is better to use
            // the following parameter to send filename in UTF-8
            'fileName'      => $filename,
            // The following is needed only by older filters versions
            'fileExtension' => $extension
        );

        $filtersResponse = self::sendToFilters(array($data), self::SOURCE_TO_XLIFF_ENDPOINT);

        return $filtersResponse[0];
    }

    public static function xliffToTarget($xliffsData) {
        $dataGroups = array();
        $tmpFiles = array();

        foreach ($xliffsData as $id => $xliffData ) {
            // Filters are expecting an upload of a xliff file, so put the xliff
            // data in a temp file and configure POST param to upload it
            $tmpXliffFile = tempnam(sys_get_temp_dir(), "matecat-xliff-to-target-");
            $tmpFiles[$id] = $tmpXliffFile;
            file_put_contents($tmpXliffFile, $xliffData[ 'document_content' ]);

            $dataGroups[$id] = array('xliffContent' => "@$tmpXliffFile");
        }

        $responses = self::sendToFilters($dataGroups, self::XLIFF_TO_TARGET_ENDPOINT);

        // We sent requests and obtained responses, we can delete temp files
        foreach ($tmpFiles as $tmpFile) {
            unlink($tmpFile);
        }

        return $responses;
    }

    /**
     * Logs a conversion to xliff, doing also file backup in case of failure.
     */
    public static function logConversionToXliff($response, $sentFile, $sourceLang, $targetLang, $segmentation ) {
        self::logConversion($response, true, $sentFile, array('source' => $sourceLang, 'target' => $targetLang), array('segmentation_rule' => $segmentation));
    }

    /**
     * Logs a conversion to target, doing also file backup in case of failure.
     */
    public static function logConversionToTarget($response, $sentFile, $jobData, $sourceFileData ) {
        self::logConversion($response, false, $sentFile, $jobData, $sourceFileData);
    }

    /**
     * Logs every conversion made. In order to make this method work, ensure
     * you have the matecat_conversions_log database properly configured.
     * See /lib/Model/matecat_conversions_log.sql
     */
    private static function logConversion($response, $toXliff, $sentFile, $jobData, $sourceFileData ) {
        try {
            $conn = new PDO( 'mysql:dbname=matecat_conversions_log;host=' . INIT::$DB_SERVER,
              INIT::$DB_USER, INIT::$DB_PASS, array (
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_ORACLE_NULLS       => true,
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION
                ) );
        } catch ( Exception $ex ) {
            Log::doLog( 'Unable to connect to matecat_conversions_log database: ' . $ex->getMessage() );
            return;
        }

        $info = array(
                'filters_address'  => @$response[ 'instanceAddress' ],
                'filters_version'  => @$response[ 'instanceVersion' ],
                'client_ip'        => Utils::getRealIpAddr(),
                'to_xliff'         => $toXliff,
                'success'          => ( $response[ 'isSuccess' ] === true ),
                'error_message'    => @$response[ 'errorMessage' ],
                'conversion_time'  => $response[ 'time' ],
                'sent_file_size'   => filesize( $sentFile ),
                'source_lang'      => $jobData[ 'source' ],
                'target_lang'      => $jobData[ 'target' ],
                'job_id'           => $jobData[ 'id' ],
                'job_pwd'          => $jobData[ 'password' ],
                'job_owner'        => $jobData[ 'owner' ],
                'source_file_id'   => ( $toXliff ? null : $sourceFileData[ 'id_file' ] ),
                'source_file_name' => ( $toXliff ? FilesStorage::basename_fix( $sentFile ) : $sourceFileData[ 'filename' ] ),
                'source_file_ext'  => ( $toXliff ? FilesStorage::pathinfo_fix( $sentFile, PATHINFO_EXTENSION ) : $sourceFileData[ 'mime_type' ] ),
                'source_file_sha1' => ( $toXliff ? sha1_file( $sentFile ) : $sourceFileData[ 'sha1_original_file' ] ),
        );

        $query = 'INSERT INTO conversions_log ('
            . implode( ", ", array_keys( $info ) )
            . ') VALUES ('
            . implode( ", ", array_fill( 0, count( $info ), "?" ) )
            . ');';

        try {
            $preparedStatement = $conn->prepare( $query );
            $preparedStatement->execute( array_values( $info ) );
            Log::doLog( $info );
        } catch ( Exception $ex ) {
            Log::doLog( "Unable to log the conversion: " . $ex->getMessage() );
        }

        if ( $response[ 'isSuccess' ] !== true ) {

            if ( INIT::$FILTERS_EMAIL_FAILURES ) {
                Utils::sendErrMailReport( "MateCat: conversion failed.\n\n" . print_r( $info, true ) );
            }

            self::backupFailedConversion( $sentFile );

        }

    }

    /**
     * Moves $sentFile to the backup folder, that is like
     *   $STORAGE_DIR/conversion_errors/YYYYMMDD/HHmmSS-filename.ext
     */
    private static function backupFailedConversion( &$sentFile ) {

        $backupDir = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR
                . 'conversion_errors' . DIRECTORY_SEPARATOR
                . date( "Ymd" );
        if ( !is_dir( $backupDir ) ) {
            mkdir( $backupDir, 0755, true );
        }

        $backupFile = $backupDir . DIRECTORY_SEPARATOR . date( "His" ) . '-' . basename( $sentFile );

        if ( !rename( $sentFile, $backupFile ) ) {
            Log::doLog( 'Unable to backup failed conversion source file ' . $sentFile . ' to ' . $backupFile );
        } else {
            $sentFile = $backupFile;
        }
    }

}