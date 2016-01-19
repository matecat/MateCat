<?php

class Filters {

    const SEND_ERROR_REPORT = true;

    const SOURCE_TO_XLIFF_ENDPOINT = "/AutomationService/original2xliff";
    const XLIFF_TO_TARGET_ENDPOINT = "/AutomationService/xliff2original";

    private static function sendToFilters( $dataGroups, $fullUrl ) {
        $multiCurl = new MultiCurlHandler();

        foreach ($dataGroups as $id => $data) {
            $options = array (
                CURLOPT_POST => true,
                CURLOPT_USERAGENT => INIT::$FILTERS_USER_AGENT,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SAFE_UPLOAD => false,
                CURLOPT_POSTFIELDS => $data,
                // Useful to debug the endpoint on the other end
                //CURLOPT_COOKIE => 'XDEBUG_SESSION=PHPSTORM'
            );
            $multiCurl->createResource( $fullUrl, $options, $id );
            $multiCurl->setRequestHeader( $id );
        }

        $multiCurl->multiExec();
        $responses = $multiCurl->getAllContents();
        $infos = $multiCurl->getAllInfo();
        $headers = $multiCurl->getAllHeaders();

        foreach ($responses as $id => &$response) {
            $info = $infos[$id];
            if ($response !== false) {
                $response = json_decode($response, true);
                //get the binary result from converter and set the right ( EXTERNAL TO THIS CLASS ) key for content
                if ( isset( $response[ "documentContent" ] ) ) {
                    $response[ 'document_content' ] = base64_decode( $response[ 'documentContent' ] );
                    unset( $response[ 'documentContent' ] );
                }
            } else {
                $response = array(
                        "isSuccess" => false,
                        "errorMessage" => "Curl error $info[errno]: $info[error]",
                        "curlInfo" => $info);
            }
            $instanceInfo = self::extractInstanceInfoFromHeaders($headers[$id]);
            if ($instanceInfo !== false) {
                $response = array_merge($response, $instanceInfo);
            }
            $response['time'] = round($info['curlinfo_total_time'] * 1000);
        }

        return $responses;
    }

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

    public static function sourceToXliff( $filePath, $sourceLang, $targetLang ) {
        $filename  = FilesStorage::pathinfo_fix( $filePath, PATHINFO_FILENAME );
        $extension = FilesStorage::pathinfo_fix( $filePath, PATHINFO_EXTENSION );

        $data = array(
            'documentContent' => "@$filePath;filename=$filename.$extension",
            'sourceLocale' => Langs_Languages::getInstance()->getLangRegionCode( $sourceLang ),
            'targetLocale' => Langs_Languages::getInstance()->getLangRegionCode( $targetLang ),
            // The following 2 are needed only by older filters versions
            'fileExtension' => $extension,
            'fileName'      => "$filename.$extension"
        );

        $url = INIT::$FILTERS_ADDRESS . self::SOURCE_TO_XLIFF_ENDPOINT;
        $filtersResponse = self::sendToFilters(array($data), $url);

        return $filtersResponse[0];
    }

    public static function xliffToTarget($xliffsData) {
        $dataGroups = array();
        $tmpFiles = array();
        $url = INIT::$FILTERS_ADDRESS . self::XLIFF_TO_TARGET_ENDPOINT;

        //iterate files.
        //For each file prepare a curl resource
        foreach ($xliffsData as $id => $xliffData ) {
            //get random name for temporary location
            $tmpXliffFile = tempnam(sys_get_temp_dir(), "matecat-xliff-to-target-");
            $tmpFiles[$id] = $tmpXliffFile;
            file_put_contents($tmpXliffFile, $xliffData[ 'document_content' ]);

            $dataGroups[$id] = array('xliffContent' => "@$tmpXliffFile");
        }

        $responses = self::sendToFilters($dataGroups, $url);

        //remove temporary files
        foreach ($tmpFiles as $tmpFile) {
            unlink($tmpFile);
        }

        return $responses;
    }

    public static function logConversionToXliff($response, $sentFile, $sourceLang, $targetLang, $segmentation ) {
        self::logConversion($response, true, $sentFile, array('source' => $sourceLang, 'target' => $targetLang), array('segmentation_rule' => $segmentation));
    }

    public static function logConversionToTarget($response, $sentFile, $jobData, $sourceFileData ) {
        self::logConversion($response, false, $sentFile, $jobData, $sourceFileData);
    }

    private static function logConversion($response, $toXliff, $sentFile, $jobData, $sourceFileData ) {
        try {
            $conn = new PDO( 'mysql:dbname=matecat_conversions_log;host=' . INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS,
                array (
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
            'filters_address' => @$response['instanceAddress'],
            'filters_version' => @$response['instanceVersion'],
            'client_ip' => Utils::getRealIpAddr(),
            'to_xliff' => $toXliff,
            'success' => ($response['isSuccess'] === true),
            'error_message' => $response['errorMessage'],
            'conversion_time' => $response['time'],
            'sent_file_size' => filesize($sentFile),
            'source_lang' => $jobData['source'],
            'target_lang' => $jobData['target'],
            'job_id' => $jobData['id'],
            'job_pwd' => $jobData['password'],
            'job_owner' => $jobData['owner'],
            'source_file_id' => ($toXliff ? null : $sourceFileData['id_file']),
            'source_file_name' => ($toXliff ? basename($sentFile) : $sourceFileData['filename']),
            'source_file_ext' => ($toXliff ? pathinfo($sentFile, PATHINFO_EXTENSION) : $sourceFileData['mime_type']),
            'source_file_sha1' => ($toXliff ? sha1_file($sentFile) : $sourceFileData['sha1_original_file']),
        );

        $query = 'INSERT INTO conversions_log ('
            . implode( ", ", array_keys( $info ) )
            . ') VALUES ('
            . implode( ", ", array_fill( 0, count( $info ), "?" ) )
            . ');';

        try {
            $preparedStatement = $conn->prepare( $query );
            $preparedStatement->execute( array_values($info) );
        } catch ( Exception $ex ) {
            Log::doLog( "Unable to log the conversion: " . $ex->getMessage() );
        }

        Utils::sendErrMailReport("MateCat: conversion failed.\n\n" . print_r($info, true));

        if ($response['isSuccess'] !== true) {
            $backupDir = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR
              . 'conversion_errors' . DIRECTORY_SEPARATOR
              . date("Ymd");
            if ( !is_dir( $backupDir ) ) {
                mkdir( $backupDir, 0755, true );
            }
            $backupFile = $backupDir . DIRECTORY_SEPARATOR . date("His") . '-' . basename($sentFile);
            if (!rename($sentFile, $backupFile)) {
                Log::doLog( 'Unable to backup failed conversion source file ' . $sentFile . ' to ' . $backupFile );
            }
        }

    }

}