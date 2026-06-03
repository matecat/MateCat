<?php

namespace Matecat\Core\Utils\Validator;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Swaggest\JsonSchema\Exception\Error;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;

class JSONValidatorExceptionTest extends AbstractTest
{
    private function makeError(): Error
    {
        $error = new Error();
        $error->error = 'Field required';
        $error->dataPointer = '/name';
        $error->schemaPointers = ['#/properties/name'];
        $error->processingPath = '#->properties:name';
        $error->subErrors = [];

        return $error;
    }

    #[Test]
    public function getFormattedErrorReturnsExpectedShape(): void
    {
        $ex = new JSONValidatorException($this->makeError());

        $formatted = $ex->getFormattedError('templates');

        $this->assertSame('/name', $formatted['node']);
        $this->assertSame('/api/v3/templates/schema', $formatted['schema']);
        $this->assertSame('Field required', $formatted['error']);
        $this->assertSame(['#/properties/name'], $formatted['schemaPointers']);
    }

    #[Test]
    public function gettersReturnCorrectValues(): void
    {
        $ex = new JSONValidatorException($this->makeError());

        $this->assertSame('Field required', $ex->getError());
        $this->assertSame('/name', $ex->getDataPointer());
        $this->assertSame(['#/properties/name'], $ex->getSchemaPointers());
        $this->assertSame('#->properties:name', $ex->getProcessingPath());
        $this->assertSame([], $ex->getSubErrors());
    }

    #[Test]
    public function jsonSerializeReturnsExpectedKeys(): void
    {
        $ex = new JSONValidatorException($this->makeError());

        $json = $ex->jsonSerialize();

        $this->assertArrayHasKey('error', $json);
        $this->assertArrayHasKey('schemaPointers', $json);
        $this->assertArrayHasKey('dataPointer', $json);
        $this->assertArrayHasKey('processingPath', $json);
        $this->assertArrayHasKey('subErrors', $json);
    }

    #[Test]
    public function messageContainsValidationError(): void
    {
        $ex = new JSONValidatorException($this->makeError());

        $this->assertStringContainsString('JSON Validation Error', $ex->getMessage());
    }

    // --- JsonValidatorGenericException ---

    #[Test]
    public function genericExceptionWithMessage(): void
    {
        $ex = new JsonValidatorGenericException('bad input');

        $this->assertSame('bad input', $ex->getMessage());
        $this->assertSame('bad input', $ex->jsonSerialize());
    }

    #[Test]
    public function genericExceptionWithNull(): void
    {
        $ex = new JsonValidatorGenericException();

        $this->assertSame('', $ex->getMessage());
        $this->assertNull($ex->jsonSerialize());
    }
}
