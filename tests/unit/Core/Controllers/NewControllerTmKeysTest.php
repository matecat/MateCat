<?php

namespace Matecat\Core\Controllers;

use Controller\API\V1\NewController;
use Exception;
use InvalidArgumentException;
use Klein\DataCollection\DataCollection;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

class TmKeysTestableNewController extends NewController
{
    public function callValidateTmAndKeys(string $private_tm_key = '', string $private_tm_key_json = ''): array
    {
        return $this->validateTmAndKeys($private_tm_key, $private_tm_key_json);
    }
}

class NewControllerTmKeysTest extends AbstractTest
{
    private TmKeysTestableNewController $controller;
    private ReflectionClass $reflector;

    public function setUp(): void
    {
        parent::setUp();

        $this->reflector = new ReflectionClass(TmKeysTestableNewController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        // Set user with uid=0 to skip MemoryKeyDao lookups (0 is falsy in PHP)
        $user = new UserStruct();
        $user->uid = 0;
        $user->email = 'test@example.com';
        $userProp = $this->reflector->getProperty('user');
        $userProp->setValue($this->controller, $user);

        // Set request mock with empty files
        $filesBag = $this->createStub(DataCollection::class);
        $filesBag->method('all')->willReturn([]);
        $requestMock = $this->createStub(Request::class);
        $requestMock->method('files')->willReturn($filesBag);

        $requestProp = $this->reflector->getProperty('request');
        $requestProp->setValue($this->controller, $requestMock);
    }

    #[Test]
    public function validateTmAndKeys_empty_string_returns_empty_key_array(): void
    {
        $result = $this->controller->callValidateTmAndKeys('', '');

        $this->assertNull($result[0]);    // private_tm_user
        $this->assertNull($result[1]);    // private_tm_pass
        $this->assertEmpty($result[2]);   // private_tm_key array
        $this->assertEmpty($result[3]);   // new_keys
        $this->assertNull($result[4]);    // tm_prioritization
    }

    #[Test]
    public function validateTmAndKeys_single_key_string_parsed(): void
    {
        $result = $this->controller->callValidateTmAndKeys('abc123', '');

        $this->assertCount(1, $result[2]);
        $this->assertSame('abc123', $result[2][0]['key']);
    }

    #[Test]
    public function validateTmAndKeys_multiple_keys_comma_separated(): void
    {
        $result = $this->controller->callValidateTmAndKeys('abc:r,def:w', '');

        $this->assertCount(2, $result[2]);
    }

    #[Test]
    public function validateTmAndKeys_too_many_keys_throws(): void
    {
        $keys = implode(',', array_map(fn ($i) => "key$i", range(1, 16)));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Too much keys provided');
        $this->controller->callValidateTmAndKeys($keys, '');
    }

    #[Test]
    public function validateTmAndKeys_invalid_private_tm_key_json_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->controller->callValidateTmAndKeys('', 'not-valid-json');
    }

    #[Test]
    public function validateTmAndKeys_valid_json_keys_parsed(): void
    {
        $json = json_encode([
            'tm_prioritization' => false,
            'keys' => [
                [
                    'key' => 'abc',
                    'read' => true,
                    'write' => false,
                    'penalty' => 0,
                ],
            ],
        ]);

        $result = $this->controller->callValidateTmAndKeys('', $json);

        $this->assertCount(1, $result[2]);
        $this->assertSame('abc', $result[2][0]['key']);
        $this->assertFalse($result[4]); // tm_prioritization
    }
}
