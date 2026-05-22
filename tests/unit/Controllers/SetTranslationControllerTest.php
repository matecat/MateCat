<?php

namespace unit\Controllers;

use Controller\API\App\SetTranslationController;
use Klein\Request;
use Klein\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use TestHelpers\AbstractTest;

/**
 * Testable subclass that exposes the suggestion_array filtering logic
 * from validateTheRequest() for isolated unit testing.
 */
class TestableSetTranslationController extends SetTranslationController
{
    public function __construct()
    {
        // skip parent constructor
    }

    public function initWith(Request $request, Response $response): void
    {
        $ref = new ReflectionClass(SetTranslationController::class);
        $ref->getProperty('request')->setValue($this, $request);
        $ref->getProperty('response')->setValue($this, $response);
    }

    /**
     * Exposes the suggestion_array filtering logic extracted from validateTheRequest().
     * This replicates the exact filtering applied to the `suggestion_array` parameter.
     */
    public function filterSuggestionArray(?string $rawInput): ?string
    {
        $suggestion_array = filter_var(
            $rawInput,
            FILTER_UNSAFE_RAW,
            ['flags' => FILTER_NULL_ON_FAILURE]
        );
        $suggestion_array = (is_string($suggestion_array) && $suggestion_array !== '' && json_decode($suggestion_array) === null) ? null : $suggestion_array;

        return $suggestion_array;
    }
}

class SetTranslationControllerTest extends AbstractTest
{
    private TestableSetTranslationController $controller;

    public function setUp(): void
    {
        parent::setUp();
        $this->controller = new TestableSetTranslationController();
    }

    public static function validSuggestionArrayProvider(): array
    {
        return [
            'simple JSON array' => [
                '[{"match":"MT","translation":"Hello","created_by":"MT-Lara"}]',
            ],
            'JSON with emojis in translation field' => [
                '[{"target_note":"","memory_key":"","prop":[],"last_updated_by":"","match":"MT","penalty":null,"data":null,"ICE":false,"reference":"","subject":"","created_by":"MT-Lara","usage_count":0,"create_date":"2026-01-29","target":"hr-HR","translation":"Tvoja tjedna kupovina, u par minuta 🛒✨"}]',
            ],
            'JSON with multiple emojis' => [
                '[{"translation":"Test 😀🎉🚀 emoji","match":"85%","created_by":"TM-user"}]',
            ],
            'JSON with 4-byte unicode characters' => [
                '[{"translation":"Text with 𝄞 musical symbol","match":"MT","created_by":"MT-Engine"}]',
            ],
            'empty array' => [
                '[]',
            ],
            'empty string' => [
                '',
            ],
        ];
    }

    public static function invalidSuggestionArrayProvider(): array
    {
        return [
            'truncated JSON' => [
                '[{"target_note":"","memory_key":"","prop":[],"last_updated_by":"","match":"MT","penalty":null,"data":null,"ICE":false,"reference":"","subject":"","created_by":"MT-Lara","usage_count":0,"create_date":"2026-01-29","target":"hr-HR","translation":"Tvoja tjedna kupovina, u par minuta ',
            ],
            'plain text' => [
                'this is not json',
            ],
            'malformed JSON missing closing bracket' => [
                '[{"translation":"hello"',
            ],
        ];
    }

    #[Test]
    #[DataProvider('validSuggestionArrayProvider')]
    public function it_preserves_valid_suggestion_array(
        ?string $input,
    ): void {
        $result = $this->controller->filterSuggestionArray($input);

        $this->assertSame($input, $result);
    }

    #[Test]
    #[DataProvider('invalidSuggestionArrayProvider')]
    public function it_nullifies_invalid_json_suggestion_array(
        string $input,
    ): void {
        $result = $this->controller->filterSuggestionArray($input);

        $this->assertNull($result, "Invalid JSON should be nullified: $input");
    }

    #[Test]
    public function it_returns_empty_string_for_null_input(): void
    {
        // filter_var(null, FILTER_UNSAFE_RAW) returns '' — this matches the real request behavior
        $result = $this->controller->filterSuggestionArray(null);

        $this->assertSame('', $result);
    }

    #[Test]
    public function it_preserves_emoji_characters_in_suggestion_array(): void
    {
        $jsonWithEmoji = '[{"target_note":"","memory_key":"","prop":[],"last_updated_by":"","match":"MT","penalty":null,"data":null,"ICE":false,"reference":"","subject":"","created_by":"MT-Lara","usage_count":0,"create_date":"2026-01-29","target":"hr-HR","translation":"Tvoja tjedna kupovina, u par minuta 🛒✨"}]';

        $result = $this->controller->filterSuggestionArray($jsonWithEmoji);

        // Verify the JSON is preserved intact
        $this->assertSame($jsonWithEmoji, $result);

        // Verify it is still valid JSON after filtering
        $decoded = json_decode($result, true);
        $this->assertNotNull($decoded);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);

        // Verify the emoji characters are intact in the decoded translation
        $this->assertStringContainsString('🛒✨', $decoded[0]['translation']);
    }

    #[Test]
    public function it_rejects_broken_json_that_simulates_emoji_corruption(): void
    {
        // This simulates what FILTER_SANITIZE_SPECIAL_CHARS used to produce:
        // JSON gets truncated/corrupted when emojis (4-byte UTF-8 sequences) are stripped
        $brokenJson = '[{"target_note":"","memory_key":"","prop":[],"last_updated_by":"","match":"MT","penalty":null,"data":null,"ICE":false,"reference":"","subject":"","created_by":"MT-Lara","usage_count":0,"create_date":"2026-01-29","target":"hr-HR","translation":"Tvoja tjedna kupovina, u par minuta ';

        $result = $this->controller->filterSuggestionArray($brokenJson);

        $this->assertNull($result, 'Broken/truncated JSON from emoji corruption must be nullified');
    }
}

