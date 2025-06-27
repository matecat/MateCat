<?php

namespace Model\Conversion;

use CURLFile;
use Exception;
use INIT;
use Langs\Languages;
use Log;
use Model\FilesStorage\AbstractFilesStorage;
use Model\Filters\DTO\IDto;
use Model\Jobs\JobStruct;
use MultiCurlHandler;
use PDO;
use Utils;

class Filters {

    const SOURCE_TO_XLIFF_ENDPOINT = "/api/v2/original2xliff";
    const XLIFF_TO_TARGET_ENDPOINT = "/api/v2/xliff2translated";

    /**
     * @param $dataGroups       array each value must be an associative array
     *                          representing the fields of a POST request
     * @param $endpoint         string use one of the two constants of this class
     *
     * @return array
     */
    private static function sendToFilters( array $dataGroups, string $endpoint ): array {
        $multiCurl = new MultiCurlHandler();

        // Each group is a POST request
        foreach ( $dataGroups as $id => $data ) {
            // Add to POST fields the version forced using the config file
            if ( $endpoint === self::SOURCE_TO_XLIFF_ENDPOINT
                    && !empty( INIT::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION ) ) {
                $data[ 'forceVersion' ] = INIT::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION;
            }

            // Setup CURL options and add to MultiCURL
            $options = [
                    CURLOPT_POST           => true,
                    CURLOPT_USERAGENT      => INIT::$FILTERS_USER_AGENT,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POSTFIELDS     => $data,
                // Useful to debug the endpoint on the other end
                //CURLOPT_COOKIE => 'XDEBUG_SESSION=PHPSTORM'
            ];
            if ( !empty( INIT::$FILTERS_RAPIDAPI_KEY ) ) {
                $options[ CURLOPT_HTTPHEADER ] = [
                        'X-RapidAPI-Host: ' . parse_url( INIT::$FILTERS_ADDRESS )[ 'host' ],
                        'X-RapidAPI-Key: ' . INIT::$FILTERS_RAPIDAPI_KEY,
                ];
            }

            $url = rtrim( INIT::$FILTERS_ADDRESS, '/' ) . $endpoint;
            Log::doJsonLog( "Calling: " . $url );
            $multiCurl->createResource( $url, $options, $id );
            $multiCurl->setRequestHeader( $id );
        }

        // Launch the multiCURL and get all the results
        $multiCurl->multiExec();
        $responses = $multiCurl->getAllContents();
        $infos     = $multiCurl->getAllInfo();
        $headers   = $multiCurl->getAllHeaders();

        // Compute results
        foreach ( $responses as $id => &$response ) {
            $info             = $infos[ $id ];
            $originalResponse = json_decode( $response );

            // Compute response
            if ( $info[ 'http_code' ] != 200 || $response === false ) {
                $errResponse = [ "isSuccess" => false, "curlInfo" => $info ];
                if ( $response === '{"message":"Invalid RapidAPI Key"}' ) {
                    $errResponse[ 'errorMessage' ] = "Failed RapidAPI authentication. Check FILTERS_RAPIDAPI_KEY in config.ini";
                } elseif ( isset( $originalResponse->errorMessage ) ) {
                    $errResponse[ 'errorMessage' ] = self::formatErrorMessage( $originalResponse->errorMessage );
                } else {
                    if ( $info[ 'errno' ] ) {
                        $errResponse[ 'errorMessage' ] = "Curl error $info[errno]: $info[error]";
                    } else {
                        $errResponse[ 'errorMessage' ] = "Received status code $info[http_code]";
                    }
                }
                $response = $errResponse;

            } else {
                $response = json_decode( $response, true );
                // Super ugly, but this is the way the old FileFormatConverter
                // cooperated with callers; changing this requires changing the
                // callers, a huge and risky refactoring work I couldn't afford
                if ( isset( $response[ "document" ] ) ) {
                    $response[ 'document_content' ] = base64_decode( $response[ 'document' ] );
                    unset( $response[ 'document' ] );
                }
            }

            // Compute headers
            $instanceInfo = self::extractInstanceInfoFromHeaders( $headers[ $id ] );
            if ( isset( $instanceInfo ) ) {
                $response = array_merge( $response, $instanceInfo );
            }

            // Add to response the CURL total time in milliseconds
            $response[ 'time' ] = round( $info[ 'curlinfo_total_time' ] * 1000 );
        }

        return $responses;
    }

    /**
     * @param string $error
     *
     * @return string
     */
    private static function formatErrorMessage( string $error ): string {

        // Error from Excel files
        return str_replace( "net.translated.matecat.filters.ExtendedExcelException: ", "", $error );
    }

    /**
     * Looks for the Filters-Instance header and returns the information in it.
     *
     * @param array $headers
     *
     * @return array|null an array with the address and version of the
     *                    respondent instance; false if the header was not found
     */
    private static function extractInstanceInfoFromHeaders( array $headers ): ?array {
        foreach ( $headers as $header ) {
            if ( preg_match( "|^Filters-Instance: address=([^;]+); version=(.+)$|", $header, $matches ) ) {
                return [
                        'instanceAddress' => $matches[ 1 ],
                        'instanceVersion' => $matches[ 2 ]
                ];
            }
        }

        return null;
    }

    /**
     * Convert the source file to XLIFF
     *
     * @param string    $filePath
     * @param string    $sourceLang
     * @param string    $targetLang
     * @param ?string   $segmentation
     * @param IDto|null $extractionParams
     *
     * @return mixed
     */
    public static function sourceToXliff( string $filePath, string $sourceLang, string $targetLang, ?string $segmentation = null, IDto $extractionParams = null ) {
        $basename  = AbstractFilesStorage::pathinfo_fix( $filePath, PATHINFO_FILENAME );
        $extension = AbstractFilesStorage::pathinfo_fix( $filePath, PATHINFO_EXTENSION );
        $filename  = "$basename.$extension";

        $data = [
                'document'     => new CURLFile( $filePath ),
                'sourceLocale' => Languages::getInstance()->getLangRegionCode( $sourceLang ),
                'targetLocale' => Languages::getInstance()->getLangRegionCode( $targetLang ),
                'segmentation' => $segmentation,
                'utf8FileName' => $filename
        ];

        if ( $extractionParams !== null ) {
            $data[ 'extractionParams' ] = json_encode( $extractionParams );
        }

        $filtersResponse = self::sendToFilters( [ $data ], self::SOURCE_TO_XLIFF_ENDPOINT );

        return $filtersResponse[ 0 ];
    }

    /**
     * @param array $xliffsData
     *
     * @return array
     */
    public static function xliffToTarget( array $xliffsData ): array {
        $dataGroups = [];
        $tmpFiles   = [];

        foreach ( $xliffsData as $id => $xliffData ) {

            // Filters are expecting an upload of a xliff file, so put the xliff
            // data in a temp file and configure POST param to upload it
            $tmpXliffFile = tempnam( sys_get_temp_dir(), "matecat-xliff-to-target-" );


            $tmpFiles[ $id ] = $tmpXliffFile;
            file_put_contents( $tmpXliffFile, $xliffData[ 'document_content' ] );

            $dataGroups[ $id ] = [ 'xliff' => new CURLFile( $tmpXliffFile ) ];
        }

        $responses = self::sendToFilters( $dataGroups, self::XLIFF_TO_TARGET_ENDPOINT );

        // We sent requests and obtained responses, we can delete temp files
        foreach ( $tmpFiles as $tmpFile ) {
            unlink( $tmpFile );
        }

        return $responses;
    }

    /**
     * Logs a conversion to xliff, doing also file backup in case of failure.
     *
     * @param             $response
     * @param string      $sentFile
     * @param string      $sourceLang
     * @param string      $targetLang
     * @param string|null $segmentation
     * @param             $extractionParameters
     *
     * @throws Exception
     */
    public static function logConversionToXliff( $response, string $sentFile, string $sourceLang, string $targetLang, ?string $segmentation, $extractionParameters ) {

        // @TODO $extractionParameters to MySQL table?
        self::logConversion( $response, true, $sentFile, [ 'source' => $sourceLang, 'target' => $targetLang ], [ 'segmentation_rule' => $segmentation ] );
    }

    /**
     * Logs a conversion to target, doing also file backup in case of failure.
     *
     * @param                $response
     * @param string         $sentFile
     * @param JobStruct      $jobData
     * @param array          $sourceFileData
     *
     * @throws Exception
     */
    public static function logConversionToTarget( $response, string $sentFile, JobStruct $jobData, array $sourceFileData ) {
        self::logConversion( $response, false, $sentFile, $jobData->toArray(), $sourceFileData );
    }

    /**
     * Logs every conversion made. In order to make this method work, ensure
     * you have the matecat_conversions_log database properly configured.
     * See /lib/Model/matecat_conversions_log.sql
     *
     * @param array  $response
     * @param bool   $toXliff
     * @param string $sentFile
     * @param array  $jobData
     * @param array  $sourceFileData
     *
     * @throws Exception
     */
    private static function logConversion( array $response, bool $toXliff, string $sentFile, array $jobData, array $sourceFileData ) {
        try {
            $conn = new PDO( 'mysql:dbname=matecat_conversions_log;host=' . INIT::$DB_SERVER,
                    INIT::$DB_USER, INIT::$DB_PASS, [
                            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
                            PDO::ATTR_EMULATE_PREPARES   => false,
                            PDO::ATTR_ORACLE_NULLS       => true,
                            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION
                    ] );
        } catch ( Exception $ex ) {
            Log::doJsonLog( 'Unable to connect to matecat_conversions_log database: ' . $ex->getMessage() );

            return;
        }

        $info = [
                'filters_address'  => $response[ 'instanceAddress' ] ?? null,
                'filters_version'  => $response[ 'instanceVersion' ] ?? null,
                'client_ip'        => Utils::getRealIpAddr(),
                'to_xliff'         => $toXliff,
                'success'          => ( $response[ 'successful' ] === true ),
                'error_message'    => $response[ 'errorMessage' ] ?? null,
                'conversion_time'  => $response[ 'time' ],
                'sent_file_size'   => filesize( $sentFile ),
                'source_lang'      => $jobData[ 'source' ],
                'target_lang'      => $jobData[ 'target' ],
                'job_id'           => $jobData[ 'id' ] ?? null,
                'job_pwd'          => $jobData[ 'password' ] ?? null,
                'job_owner'        => $jobData[ 'owner' ] ?? null,
                'source_file_id'   => ( $toXliff ? null : $sourceFileData[ 'id_file' ] ),
                'source_file_name' => ( $toXliff ? AbstractFilesStorage::basename_fix( $sentFile ) : $sourceFileData[ 'filename' ] ),
                'source_file_ext'  => ( $toXliff ? AbstractFilesStorage::pathinfo_fix( $sentFile, PATHINFO_EXTENSION ) : $sourceFileData[ 'mime_type' ] ),
                'source_file_sha1' => ( $toXliff ? sha1_file( $sentFile ) : $sourceFileData[ 'sha1_original_file' ] ),
                'segmentation'     => $sourceFileData[ 'segmentation_rule' ] ?? null,
        ];

        $query = 'INSERT INTO conversions_log ('
                . implode( ", ", array_keys( $info ) )
                . ') VALUES ('
                . implode( ", ", array_fill( 0, count( $info ), "?" ) )
                . ');';

        try {
            $preparedStatement = $conn->prepare( $query );
            $preparedStatement->execute( array_values( $info ) );
            Log::doJsonLog( $info );
        } catch ( Exception $ex ) {
            Log::doJsonLog( "Unable to log the conversion: " . $ex->getMessage() );
        }

        if ( $response[ 'successful' ] !== true ) {

            if ( INIT::$FILTERS_EMAIL_FAILURES ) {
                Utils::sendErrMailReport( "Matecat: conversion failed.\n\n" . print_r( $info, true ) );
            }

            self::backupFailedConversion( $sentFile );
        }
    }

    /**
     * Moves $sentFile to the backup folder, that is like
     *   $STORAGE_DIR/conversion_errors/YYYYMMDD/HHmmSS-filename.ext
     *
     * @param string $sentFile
     */
    private static function backupFailedConversion( string &$sentFile ) {

        $backupDir = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR
                . 'conversion_errors' . DIRECTORY_SEPARATOR
                . date( "Ymd" );
        if ( !is_dir( $backupDir ) ) {
            mkdir( $backupDir, 0755, true );
        }

        $backupFile = $backupDir . DIRECTORY_SEPARATOR . date( "His" ) . '-' . basename( $sentFile );

        if ( !rename( $sentFile, $backupFile ) ) {
            Log::doJsonLog( 'Unable to backup failed conversion source file ' . $sentFile . ' to ' . $backupFile );
        } else {
            $sentFile = $backupFile;
        }
    }

}