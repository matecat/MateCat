<?php

namespace FilesStorage;

use SimpleS3\Client;

class S3FilesStorage extends AbstractFilesStorage {
    /**
     * @var Client
     */
    private $s3Client;

    /**
     * S3FilesStorage constructor.
     */
    public function __construct() {
        // init the S3Client
        $awsAccessKeyId = \INIT::$AWS_ACCESS_KEY_ID;
        $awsSecretKey   = \INIT::$AWS_SECRET_KEY;
        $awsVersion     = \INIT::$AWS_VERSION;
        $awsRegion      = \INIT::$AWS_REGION;

        $this->s3Client = new Client(
                $awsAccessKeyId,
                $awsSecretKey,
                [
                        'version' => $awsVersion,
                        'region'  => $awsRegion,
                ]
        );
    }

    public function moveFileFromUploadSessionToQueuePath( $upload_session ) {
        // TODO: Implement moveFileFromUploadSessionToQueuePath() method.
    }

    public function moveFromCacheToFileDir( $dateHashPath, $lang, $idFile, $newFileName = null ) {
        // TODO: Implement moveFromCacheToFileDir() method.
    }
}
