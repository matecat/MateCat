<?php

namespace unit\AiAssistant;

use TestHelpers\AbstractTest;

class ManageAlternativeTranslationTest extends AbstractTest
{
    /**
     * Tests the `manageAlternativeTranslations` method for handling alternative translations
     * of a given text. Verifies the integration with the Gemini Client, mocking necessary dependencies
     * and ensuring the response structure and values meet expected criteria.
     *
     * @return void
     * @test
     */
    public function testManageAlternativeTranslations()
    {
        $sourceLanguage = 'en-US';
        $targetLanguage = 'it-IT';
        $sourceSentence = 'The quick brown fox jumps over the lazy dog.';
        $sourceContextSentencesString = 'A simple sentence.';
        $targetSentence = 'La volpe veloce marrone salta sopra il cane pigro.';
        $targetContextSentencesString = 'Una frase semplice.';
        $excerpt = 'salta'; // user selection
        $styleInstructions = 'fluid';

        $geminiResponseText = '```json
[
  {
    "alternative": "La volpe veloce marrone balza sopra il cane pigro.",
    "context": "Più dinamico"
  }
]
```';

        // Mock response using the real class via its `from` method or by mocking it if we can
        // Since it's final and we have the IncompatibleReturnValueException,
        // we should try to create a real instance or use a mock that PHPUnit accepts.
        // The error says: "may not return value of type MockObject_MockGenerateContentResponse_fd8a3509, its declared return type is \"Gemini\Responses\GenerativeModel\GenerateContentResponse\""
        // This confirms PHPUnit's mock of our custom class doesn't satisfy the type hint of the original class.
        $generateContentResponse = \Gemini\Responses\GenerativeModel\GenerateContentResponse::from([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $geminiResponseText]
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                    'index' => 0,
                    'safetyRatings' => [],
                ]
            ],
            'usageMetadata' => [
                'promptTokenCount' => 0,
                'candidatesTokenCount' => 0,
                'totalTokenCount' => 0,
            ],
        ]);

        // Mock Gemini\Contracts\Resources\GenerativeModelContract
        $generativeModelMock = $this->createMock(\Gemini\Contracts\Resources\GenerativeModelContract::class);
        $generativeModelMock->method('generateContent')->willReturn($generateContentResponse);

        // Mock Gemini\Contracts\ClientContract
        $geminiClientMock = $this->createMock(\Gemini\Contracts\ClientContract::class);
        $geminiClientMock->method('generativeModel')->willReturn($generativeModelMock);

        $client = new \Utils\AIAssistant\GeminiClient($geminiClientMock);
        $result = $client->manageAlternativeTranslations(
            $sourceLanguage,
            $targetLanguage,
            $sourceSentence,
            $sourceContextSentencesString,
            $targetSentence,
            $targetContextSentencesString,
            $excerpt,
            $styleInstructions
        );

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('alternative', $result[0]);
        $this->assertArrayHasKey('highlighted', $result[0]);
        $this->assertArrayHasKey('context', $result[0]);
        $this->assertArrayHasKey('original', $result[0]);
        $this->assertArrayHasKey('replacement', $result[0]);

        $this->assertEquals("La volpe veloce marrone balza sopra il cane pigro.", $result[0]['alternative']);
        $this->assertEquals("balza", $result[0]['replacement']);
        $this->assertEquals("salta", $result[0]['original']);
        $this->assertEquals("Più dinamico", $result[0]['context']);
        $this->assertEquals(" ...volpe veloce marrone", $result[0]['highlighted']['before']);
        $this->assertEquals("balza", $result[0]['highlighted']['changed']);
        $this->assertEquals("sopra il cane... ", $result[0]['highlighted']['after']);
    }
}
