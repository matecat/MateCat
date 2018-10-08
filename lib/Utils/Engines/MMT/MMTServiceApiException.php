<?php
/**
 * Created by PhpStorm.
 * User: davide
 * Date: 04/10/17
 * Time: 08:59
 */

namespace Engines\MMT;

class MMTServiceApiException extends \Exception {

    public static function fromJSONResponse($json) {
        $code = isset($json['status']) ? intval($json['status']) : 500;
        $type = isset($json['error']['type']) ? $json['error']['type'] : 'UnknownException';
        $message = isset($json['error']['message']) ? $json['error']['message'] : '';

        return new self($type, $code, $message);
    }

    private $type;

    public function __construct($type, $code, $message = "") {
        parent::__construct("($type) $message", $code);
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

}