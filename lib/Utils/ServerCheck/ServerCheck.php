<?php

namespace Utils\ServerCheck;

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 17/02/14
 * Time: 15.20
 *
 */
class ServerCheck
{

    protected static ?ServerCheck $_INSTANCE = null;

    /**
     * @var UploadParams
     */
    protected static UploadParams $uploadParams;

    protected function __construct()
    {
        self::$uploadParams = new UploadParams();

        //init class loading server params
        $this->checkUploadParams();
    }

    public static function getInstance(): ServerCheck
    {
        if (self::$_INSTANCE == null) {
            self::$_INSTANCE = new self();
        }

        return self::$_INSTANCE;
    }

    protected function checkUploadParams(): ServerCheck
    {
        self::$uploadParams->setPostMaxSize($this->getByteValue(ini_get('post_max_size')));
        self::$uploadParams->setUploadMaxFilesize($this->getByteValue(ini_get('upload_max_filesize')));

        return $this;
    }

    private function getByteValue($value): int
    {
        $regexp = '/([0-9]+)([GM])?/';
        preg_match($regexp, $value, $matches);
        if (isset($matches[ 2 ])) {
            switch ($matches[ 2 ]) {
                case "M":
                    return (int)$matches[ 1 ] * 1024 * 1024;
                case "G":
                    return (int)$matches[ 1 ] * 1024 * 1024 * 1024;
            }
        }

        return (int)$matches[ 1 ];
    }

    /**
     * @return UploadParams
     */
    public function getUploadParams(): UploadParams
    {
        return clone self::$uploadParams;
    }

} 