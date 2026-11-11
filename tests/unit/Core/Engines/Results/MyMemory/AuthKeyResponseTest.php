<?php

declare(strict_types=1);

namespace Matecat\Core\Engines\Results\MyMemory;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Engines\Results\MyMemory\AuthKeyResponse;

#[Group('unit')]
class AuthKeyResponseTest extends AbstractTest
{
    #[Test]
    public function constructor_keeps_status_200_for_a_valid_key_answer(): void
    {
        $response = new AuthKeyResponse(1);

        $this->assertSame(200, $response->responseStatus);
        $this->assertSame(1, $response->responseData);
    }

    #[Test]
    public function constructor_keeps_status_200_for_an_invalid_key_answer(): void
    {
        $response = new AuthKeyResponse(0);

        $this->assertSame(200, $response->responseStatus);
        $this->assertSame(0, $response->responseData);
    }

    #[Test]
    public function constructor_keeps_status_200_for_a_null_answer(): void
    {
        $response = new AuthKeyResponse(null);

        $this->assertSame(200, $response->responseStatus);
        $this->assertSame([], $response->responseData);
    }

    #[Test]
    public function constructor_takes_the_status_of_a_curl_error(): void
    {
        $response = [
            'error' => [
                'code' => -6,
                'message' => " Could not resolve host - Server error (http status 0)",
                'response' => "",
            ],
            'responseStatus' => 0,
        ];

        $authKeyResponse = new AuthKeyResponse($response);

        $this->assertSame(0, $authKeyResponse->responseStatus);
        $this->assertSame($response, $authKeyResponse->responseData);
    }

    #[Test]
    public function constructor_takes_the_status_of_an_http_error(): void
    {
        $response = [
            'error' => [
                'code' => -6,
                'message' => " Could not resolve host - Server error (http status 0)",
                'response' => "",
            ],
            'responseStatus' => 500,
        ];

        $authKeyResponse = new AuthKeyResponse($response);

        $this->assertSame(500, $authKeyResponse->responseStatus);
        $this->assertSame($response, $authKeyResponse->responseData);
    }
}
