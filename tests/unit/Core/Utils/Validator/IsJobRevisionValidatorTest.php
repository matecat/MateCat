<?php

declare(strict_types=1);

namespace Matecat\Core\Utils\Validator;

use Controller\API\Commons\Exceptions\ValidationError;
use DomainException;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\LQA\ChunkReviewDao;
use PHPUnit\Framework\Attributes\Test;
use Utils\Validator\Contracts\ValidatorObject;
use Utils\Validator\IsJobRevisionValidator;

class IsJobRevisionValidatorTest extends AbstractTest
{
    #[Test]
    public function ValidateThrowsWhenJidMissing(): void
    {
        $validator = new IsJobRevisionValidator($this->createStub(ChunkReviewDao::class));
        $object = ValidatorObject::fromArray(['password' => 'abc123']);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Missing jid parameter');

        $validator->validate($object);
    }

    #[Test]
    public function ValidateThrowsWhenPasswordMissing(): void
    {
        $validator = new IsJobRevisionValidator($this->createStub(ChunkReviewDao::class));
        $object = ValidatorObject::fromArray(['jid' => 123]);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Missing password parameter');

        $validator->validate($object);
    }

    #[Test]
    public function ValidateThrowsWhenDataIsEmpty(): void
    {
        $validator = $this->createValidatorWithResponse(null);
        $object = ValidatorObject::fromArray(['jid' => 999, 'password' => 'nonexistent']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid combination jid/password');

        $validator->validate($object);
    }

    #[Test]
    public function ValidateThrowsWhenAllZero(): void
    {
        $data = new ShapelessConcreteStruct();
        $data->t = 0;
        $data->r1 = 0;
        $data->r2 = 0;

        $validator = $this->createValidatorWithResponse($data);
        $object = ValidatorObject::fromArray(['jid' => 1, 'password' => 'some_pass']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid combination jid/password');

        $validator->validate($object);
    }

    #[Test]
    public function ValidateReturnsObjectForR1Password(): void
    {
        $data = new ShapelessConcreteStruct();
        $data->t = 0;
        $data->r1 = 1;
        $data->r2 = 0;

        $validator = $this->createValidatorWithResponse($data);
        $object = ValidatorObject::fromArray(['jid' => 1, 'password' => 'rev_pass']);

        $result = $validator->validate($object);
        $this->assertSame($object, $result);
    }

    #[Test]
    public function ValidateReturnsObjectForR2Password(): void
    {
        $data = new ShapelessConcreteStruct();
        $data->t = 0;
        $data->r1 = 0;
        $data->r2 = 1;

        $validator = $this->createValidatorWithResponse($data);
        $object = ValidatorObject::fromArray(['jid' => 1, 'password' => 'rev2_pass']);

        $result = $validator->validate($object);
        $this->assertSame($object, $result);
    }

    #[Test]
    public function ValidateReturnsNullForTranslationPassword(): void
    {
        $data = new ShapelessConcreteStruct();
        $data->t = 1;
        $data->r1 = 0;
        $data->r2 = 0;

        $validator = $this->createValidatorWithResponse($data);
        $object = ValidatorObject::fromArray(['jid' => 1, 'password' => 'trans_pass']);

        $result = $validator->validate($object);
        $this->assertNull($result);
    }

    #[Test]
    public function constructorRequiresInjectedDao(): void
    {
        $param = (new \ReflectionMethod(IsJobRevisionValidator::class, '__construct'))->getParameters()[0];

        $this->assertFalse(
            $param->isOptional(),
            'ChunkReviewDao must be a mandatory ctor dependency (no Database::obtain() fallback)'
        );
        $this->assertFalse($param->allowsNull(), 'ChunkReviewDao ctor param must not be nullable');
    }

    private function createValidatorWithResponse(?ShapelessConcreteStruct $response): IsJobRevisionValidator
    {
        $mockDao = $this->createStub(ChunkReviewDao::class);
        $mockDao->method('isTOrR1OrR2')->willReturn($response);

        return new IsJobRevisionValidator($mockDao);
    }
}
