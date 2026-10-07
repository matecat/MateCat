<?php

namespace Matecat\Core\Traits;

use Controller\Traits\ValidatesPretranslateMatchTrait;
use InvalidArgumentException;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PretranslateMatchValidator
{
    use ValidatesPretranslateMatchTrait;

    /**
     * @param '100'|'101' $match
     *
     * @return array{lock: int, status: string}
     */
    public function validate(string $match, mixed $lock, mixed $status): array
    {
        return $this->validatePretranslateMatchParams($match, $lock, $status);
    }
}

class ValidatesPretranslateMatchTraitTest extends AbstractTest
{
    private PretranslateMatchValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PretranslateMatchValidator();
    }

    #[Test]
    public function a101MatchDefaultsToApprovedAndLocked(): void
    {
        $this->assertSame(['lock' => 1, 'status' => 'APPROVED'], $this->validator->validate('101', null, null));
    }

    #[Test]
    public function a100MatchDefaultsToTranslatedAndEditable(): void
    {
        $this->assertSame(['lock' => 0, 'status' => 'TRANSLATED'], $this->validator->validate('100', null, null));
    }

    #[Test]
    public function readsTheLockAndAStatusInAnyLetterCase(): void
    {
        $this->assertSame(['lock' => 1, 'status' => 'APPROVED2'], $this->validator->validate('100', '1', 'approved2'));
        $this->assertSame(['lock' => 0, 'status' => 'TRANSLATED'], $this->validator->validate('101', '0', 'Translated'));
    }

    /**
     * @return array<string, array{'100'|'101', mixed, mixed, string}>
     */
    public static function invalidOptionProvider(): array
    {
        return [
            'lock out of range' => ['101', '2', null, 'Invalid pretranslate_101_lock value'],
            'lock not a number' => ['100', 'yes', null, 'Invalid pretranslate_100_lock value'],
            'status new'        => ['101', null, 'NEW', 'Invalid pretranslate_101_status value'],
            'status draft'      => ['100', null, 'draft', 'Invalid pretranslate_100_status value'],
            'status unknown'    => ['100', null, 'garbage', 'Invalid pretranslate_100_status value'],
        ];
    }

    #[Test]
    #[DataProvider('invalidOptionProvider')]
    public function refusesAnInvalidOption(string $match, mixed $lock, mixed $status, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $this->expectExceptionCode(-6);

        $this->validator->validate($match, $lock, $status);
    }
}
