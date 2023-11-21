<?php

namespace Engines\DeepL;

class DeepLApiException extends \Exception
{
    public static function fromJSONResponse($json)
    {
        $code = isset($json['status']) ? intval($json['status']) : 500;
        $type = isset($json['error']['type']) ? $json['error']['type'] : 'UnknownException';
        $message = isset($json['error']['message']) ? $json['error']['message'] : '';

        return new self($type, $code, $message);
    }
}