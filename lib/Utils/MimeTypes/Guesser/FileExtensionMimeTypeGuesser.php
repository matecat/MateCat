<?php

namespace MimeTypes\Guesser;

class FileExtensionMimeTypeGuesser implements MimeTypeGuesserInterface
{
    /**
     * @inheritDoc
     */
    public function isGuesserSupported()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function guessMimeType($path)
    {
        $pathinfo = pathinfo($path);
        $basename  = basename($path);

        if(!empty($pathinfo)){
            return $pathinfo['filename'];
        }
    }
}