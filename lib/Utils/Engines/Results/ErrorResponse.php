<?php

namespace Utils\Engines\Results;

use TypeError;

class ErrorResponse
{

    public int $code = 0;
    public ?string $message = "";
    /**
     * @var string|null
     */
    public ?string $http_code = null;

    /**
     * @param mixed $result
     *
     * @throws TypeError
     */
    public function __construct(mixed $result = [])
    {
        if (!empty($result)) {
            $this->http_code = $result['http_code'] ?? null;
            $this->code = (int)($result['code'] ?? 0);
            $this->message = $result['message'] ?? null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function get_as_array(): array
    {
        return (array)$this;
    }

}