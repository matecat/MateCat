<?php

namespace Utils\Engines\Results;

class ErrorResponse
{

    public int $code = 0;
    public ?string $message = "";
    /**
     * @var string|null
     */
    public ?string $http_code = null;

    public function __construct($result = [])
    {
        if (!empty($result)) {
            $this->http_code = $result['http_code'] ?? null;
            $this->code = (int)($result['code'] ?? 0);
            $this->message = $result['message'] ?? null;
        }
    }

    public function get_as_array(): array
    {
        return (array)$this;
    }

}