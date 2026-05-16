<?php

namespace unit\Controllers;

use Controller\API\V1\NewController;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use TestHelpers\AbstractTest;

class NewControllerValidationMethodsTest extends AbstractTest
{
    private NewController $controller;
    private ReflectionClass $reflector;

    public function setUp(): void
    {
        parent::setUp();
        $requestMock = $this->createStub(Request::class);
        $responseMock = $this->createStub(Response::class);

        $this->reflector = new ReflectionClass(NewController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        $user = new UserStruct();
        $user->uid = 42;
        $user->email = 'test@example.com';
        $userProp = $this->reflector->getProperty('user');
        $userProp->setValue($this->controller, $user);
    }

    private function invokeMethod(string $name, array $args = []): mixed
    {
        $method = $this->reflector->getMethod($name);

        return $method->invokeArgs($this->controller, $args);
    }

    // ──────────────────────────────────────────────────────────────────
    // validateMetadataParam()
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function validateMetadataParam_empty_returns_array_with_word_count_type(): void
    {
        $result = $this->invokeMethod('validateMetadataParam', [null]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('word_count_type', $result);
        $this->assertSame('raw', $result['word_count_type']);
    }

    #[Test]
    public function validateMetadataParam_valid_json_returns_decoded_array(): void
    {
        $json = '{"from_api": true}';
        $result = $this->invokeMethod('validateMetadataParam', [$json]);

        $this->assertIsArray($result);
        $this->assertTrue($result['from_api']);
        $this->assertArrayHasKey('word_count_type', $result);
    }

    #[Test]
    public function validateMetadataParam_too_long_throws(): void
    {
        $longString = str_repeat('a', 2049);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('metadata string is too long');
        $this->invokeMethod('validateMetadataParam', [$longString]);
    }

    #[Test]
    public function validateMetadataParam_empty_string_returns_default(): void
    {
        $result = $this->invokeMethod('validateMetadataParam', ['']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('word_count_type', $result);
        $this->assertCount(1, $result);
    }

    // ──────────────────────────────────────────────────────────────────
    // validateCharacterCounterMode()
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function validateCharacterCounterMode_null_returns_null(): void
    {
        $result = $this->invokeMethod('validateCharacterCounterMode', [null]);
        $this->assertNull($result);
    }

    #[Test]
    public function validateCharacterCounterMode_empty_string_returns_null(): void
    {
        $result = $this->invokeMethod('validateCharacterCounterMode', ['']);
        $this->assertNull($result);
    }

    #[Test]
    public function validateCharacterCounterMode_valid_google_ads(): void
    {
        $result = $this->invokeMethod('validateCharacterCounterMode', ['google_ads']);
        $this->assertSame('google_ads', $result);
    }

    #[Test]
    public function validateCharacterCounterMode_valid_exclude_cjk(): void
    {
        $result = $this->invokeMethod('validateCharacterCounterMode', ['exclude_cjk']);
        $this->assertSame('exclude_cjk', $result);
    }

    #[Test]
    public function validateCharacterCounterMode_valid_all_one(): void
    {
        $result = $this->invokeMethod('validateCharacterCounterMode', ['all_one']);
        $this->assertSame('all_one', $result);
    }

    #[Test]
    public function validateCharacterCounterMode_invalid_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->invokeMethod('validateCharacterCounterMode', ['invalid_mode']);
    }

    // ──────────────────────────────────────────────────────────────────
    // validateSubject()
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function validateSubject_general_returns_general(): void
    {
        $result = $this->invokeMethod('validateSubject', ['general']);
        $this->assertSame('general', $result);
    }

    #[Test]
    public function validateSubject_empty_defaults_to_general(): void
    {
        $result = $this->invokeMethod('validateSubject', ['']);
        $this->assertSame('general', $result);
    }

    #[Test]
    public function validateSubject_null_defaults_to_general(): void
    {
        $result = $this->invokeMethod('validateSubject', [null]);
        $this->assertSame('general', $result);
    }

    #[Test]
    public function validateSubject_invalid_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Subject not allowed');
        $this->invokeMethod('validateSubject', ['nonexistent_subject_xyz']);
    }

    // ──────────────────────────────────────────────────────────────────
    // validateSourceLang()
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function validateSourceLang_valid_code_returns_normalized(): void
    {
        $lang_handler = \Matecat\Locales\Languages::getInstance();
        $result = $this->invokeMethod('validateSourceLang', [$lang_handler, 'en']);
        $this->assertSame('en-US', $result);
    }

    #[Test]
    public function validateSourceLang_invalid_throws(): void
    {
        $lang_handler = \Matecat\Locales\Languages::getInstance();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing source language');
        $this->invokeMethod('validateSourceLang', [$lang_handler, 'zzz-invalid']);
    }

    #[Test]
    public function validateSourceLang_empty_throws(): void
    {
        $lang_handler = \Matecat\Locales\Languages::getInstance();

        $this->expectException(InvalidArgumentException::class);
        $this->invokeMethod('validateSourceLang', [$lang_handler, '']);
    }

    // ──────────────────────────────────────────────────────────────────
    // validateTargetLangs()
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function validateTargetLangs_single_lang_returns_normalized(): void
    {
        $lang_handler = \Matecat\Locales\Languages::getInstance();
        $result = $this->invokeMethod('validateTargetLangs', [$lang_handler, 'fr']);
        $this->assertSame('fr-FR', $result);
    }

    #[Test]
    public function validateTargetLangs_multiple_comma_separated(): void
    {
        $lang_handler = \Matecat\Locales\Languages::getInstance();
        $result = $this->invokeMethod('validateTargetLangs', [$lang_handler, 'fr-FR,de-DE,it-IT']);
        $this->assertStringContainsString('fr-FR', $result);
        $this->assertStringContainsString('de-DE', $result);
        $this->assertStringContainsString('it-IT', $result);
    }

    #[Test]
    public function validateTargetLangs_deduplicates(): void
    {
        $lang_handler = \Matecat\Locales\Languages::getInstance();
        $result = $this->invokeMethod('validateTargetLangs', [$lang_handler, 'fr,fr,fr']);
        $this->assertSame('fr-FR', $result);
    }

    #[Test]
    public function validateTargetLangs_invalid_throws(): void
    {
        $lang_handler = \Matecat\Locales\Languages::getInstance();

        $this->expectException(InvalidArgumentException::class);
        $this->invokeMethod('validateTargetLangs', [$lang_handler, 'zzz-invalid']);
    }

    // ──────────────────────────────────────────────────────────────────
    // validatePublicTMPenalty()
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function validatePublicTMPenalty_valid_returns_value(): void
    {
        $result = $this->invokeMethod('validatePublicTMPenalty', [50]);
        $this->assertSame(50, $result);
    }

    #[Test]
    public function validatePublicTMPenalty_zero_returns_zero(): void
    {
        $result = $this->invokeMethod('validatePublicTMPenalty', [0]);
        $this->assertSame(0, $result);
    }

    #[Test]
    public function validatePublicTMPenalty_hundred_returns_hundred(): void
    {
        $result = $this->invokeMethod('validatePublicTMPenalty', [100]);
        $this->assertSame(100, $result);
    }

    #[Test]
    public function validatePublicTMPenalty_negative_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->invokeMethod('validatePublicTMPenalty', [-1]);
    }

    #[Test]
    public function validatePublicTMPenalty_over_100_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->invokeMethod('validatePublicTMPenalty', [101]);
    }

    #[Test]
    public function validatePublicTMPenalty_null_returns_null(): void
    {
        $result = $this->invokeMethod('validatePublicTMPenalty', [null]);
        $this->assertNull($result);
    }

    // ──────────────────────────────────────────────────────────────────
    // generateTargetEngineAssociation()
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function generateTargetEngineAssociation_single_target(): void
    {
        $result = $this->invokeMethod('generateTargetEngineAssociation', ['fr-FR', 2]);
        $this->assertSame(['fr-FR' => 2], $result);
    }

    #[Test]
    public function generateTargetEngineAssociation_multiple_targets(): void
    {
        $result = $this->invokeMethod('generateTargetEngineAssociation', ['fr-FR,de-DE,it-IT', 3]);
        $this->assertSame(['fr-FR' => 3, 'de-DE' => 3, 'it-IT' => 3], $result);
    }

    // ──────────────────────────────────────────────────────────────────
    // parseTmKeyInput()
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function parseTmKeyInput_key_only_returns_rw(): void
    {
        $result = $this->invokeMethod('parseTmKeyInput', ['abc123']);
        $this->assertSame('abc123', $result['key']);
        $this->assertTrue($result['r']);
        $this->assertTrue($result['w']);
    }

    #[Test]
    public function parseTmKeyInput_key_with_read_only(): void
    {
        $result = $this->invokeMethod('parseTmKeyInput', ['abc123:r']);
        $this->assertSame('abc123', $result['key']);
        $this->assertTrue($result['r']);
        $this->assertFalse($result['w']);
    }

    #[Test]
    public function parseTmKeyInput_key_with_write_only(): void
    {
        $result = $this->invokeMethod('parseTmKeyInput', ['abc123:w']);
        $this->assertSame('abc123', $result['key']);
        $this->assertFalse($result['r']);
        $this->assertTrue($result['w']);
    }

    #[Test]
    public function parseTmKeyInput_key_with_rw(): void
    {
        $result = $this->invokeMethod('parseTmKeyInput', ['abc123:rw']);
        $this->assertSame('abc123', $result['key']);
        $this->assertTrue($result['r']);
        $this->assertTrue($result['w']);
    }

    #[Test]
    public function parseTmKeyInput_empty_returns_null(): void
    {
        $result = $this->invokeMethod('parseTmKeyInput', ['']);
        $this->assertNull($result);
    }

    #[Test]
    public function parseTmKeyInput_invalid_permission_throws(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid permission modifier string');
        $this->invokeMethod('parseTmKeyInput', ['abc123:x']);
    }

    #[Test]
    public function parseTmKeyInput_trims_whitespace(): void
    {
        $result = $this->invokeMethod('parseTmKeyInput', ['  abc123  ']);
        $this->assertSame('abc123', $result['key']);
    }

    // ──────────────────────────────────────────────────────────────────
    // sanitizeTmKeyArr()
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function sanitizeTmKeyArr_returns_array_with_key(): void
    {
        $input = ['key' => 'test-key-123', 'r' => true, 'w' => true, 'penalty' => 0];
        $result = $this->invokeMethod('sanitizeTmKeyArr', [$input]);

        $this->assertIsArray($result);
        $this->assertSame('test-key-123', $result['key']);
    }

    #[Test]
    public function sanitizeTmKeyArr_sets_complete_format(): void
    {
        $input = ['key' => 'k1', 'r' => true, 'w' => false, 'penalty' => 5];
        $result = $this->invokeMethod('sanitizeTmKeyArr', [$input]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('key', $result);
    }

    // ──────────────────────────────────────────────────────────────────
    // validateMMTGlossaries()
    // ──────────────────────────────────────────────────────────────────

    #[Test]
    public function validateMMTGlossaries_null_returns_null(): void
    {
        $result = $this->invokeMethod('validateMMTGlossaries', [null]);
        $this->assertNull($result);
    }

    #[Test]
    public function validateMMTGlossaries_empty_returns_null(): void
    {
        $result = $this->invokeMethod('validateMMTGlossaries', ['']);
        $this->assertNull($result);
    }
}
