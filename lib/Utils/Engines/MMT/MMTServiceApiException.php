<?php
/**
 * Created by PhpStorm.
 * User: davide
 * Date: 04/10/17
 * Time: 08:59
 */

namespace Utils\Engines\MMT;

use Exception;

class MMTServiceApiException extends Exception
{

    public static function fromJSONResponse(array $json): MMTServiceApiException
    {
        $code = isset($json['status']) ? intval($json['status']) : 500;
        $type = $json['error']['type'] ?? 'UnknownException';
        $message = $json['error']['message'] ?? '';

        return new self($type, $code, $message);
    }

    private $type;

    public function __construct(?string $type = '', ?int $code = 0, ?string $message = "")
    {
        parent::__construct("($type) $message", $code);
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

}