<?php

namespace MimeTypes\Guesser;

use MimeTypes\Constants\MimeTypesMap;

class FileExtensionMimeTypeGuesser implements MimeTypeGuesserInterface
{
    /**
     * @inheritDoc
     */
    public function isGuesserSupported()
    {
        return function_exists('pathinfo');
    }

    /**
     * @inheritDoc
     */
    public function guessMimeType($path)
    {
        $pathinfo = pathinfo($path);

        if(empty($pathinfo)){
            return null;
        }

        if(!isset($pathinfo['extension'])){
            return null;
        }

        if(isset(MimeTypesMap::REVERSE_MAP[$pathinfo['extension']])){
            return MimeTypesMap::REVERSE_MAP[$pathinfo['extension']];
        }

        return null;
    }
}