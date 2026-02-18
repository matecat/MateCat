<?php

namespace unit\AiAssistant;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
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
    #[AllowMockObjectsWithoutExpectations]
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

        $result = $this->mockedGeminiResponse(
            $sourceLanguage,
            $targetLanguage,
            $sourceSentence,
            $sourceContextSentencesString,
            $targetSentence,
            $targetContextSentencesString,
            $excerpt,
            $styleInstructions,
            $geminiResponseText
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

    /**
     * Tests the `manageAlternativeTranslations` method for handling alternative translations
     * of a given text. Verifies the integration with the Gemini Client, mocking necessary dependencies
     * and ensuring the response structure and values meet expected criteria.
     *
     * @return void
     * @test
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testManageAlternativeTranslationsWithBlankAfter()
    {
        $sourceLanguage = 'en-US';
        $targetLanguage = 'it-IT';
        $sourceSentence = 'The quick brown fox jumps over the lazy dog.';
        $sourceContextSentencesString = 'A simple sentence.';
        $targetSentence = 'La volpe veloce marrone salta sopra il cane pigro.';
        $targetContextSentencesString = 'Una frase semplice.';
        $excerpt = 'lazy'; // user selection
        $styleInstructions = 'fluid';

        $geminiResponseText = '```json
[
  {
    "alternative": "La volpe veloce marrone salta sopra il cane indolente.",
    "context": "Più dinamico"
  }
]
```';

        $result = $this->mockedGeminiResponse(
            $sourceLanguage,
            $targetLanguage,
            $sourceSentence,
            $sourceContextSentencesString,
            $targetSentence,
            $targetContextSentencesString,
            $excerpt,
            $styleInstructions,
            $geminiResponseText
        );

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('alternative', $result[0]);
        $this->assertArrayHasKey('highlighted', $result[0]);
        $this->assertArrayHasKey('context', $result[0]);
        $this->assertArrayHasKey('original', $result[0]);
        $this->assertArrayHasKey('replacement', $result[0]);

        $this->assertEquals("La volpe veloce marrone salta sopra il cane indolente.", $result[0]['alternative']);
        $this->assertEquals("indolente.", $result[0]['replacement']);
        $this->assertEquals("pigro.", $result[0]['original']);
        $this->assertEquals("Più dinamico", $result[0]['context']);
        $this->assertEquals(" ...sopra il cane", $result[0]['highlighted']['before']);
        $this->assertEquals("indolente.", $result[0]['highlighted']['changed']);
        $this->assertEquals("", $result[0]['highlighted']['after']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testManageAlternativeTranslationsWithBlankBefore()
    {
        $sourceLanguage = 'en-US';
        $targetLanguage = 'it-IT';
        $sourceSentence = 'Superman is the american national hero.';
        $sourceContextSentencesString = 'A simple sentence.';
        $targetSentence = 'Superman è l\'eroe americano nazionale.';
        $targetContextSentencesString = 'Una frase semplice.';
        $excerpt = 'Superman'; // user selection
        $styleInstructions = 'fluid';

        $geminiResponseText = '```json
[
  {
    "alternative": "Spiderman è l\'eroe americano nazionale.",
    "context": "Più dinamico"
  }
]
```';

        $result = $this->mockedGeminiResponse(
            $sourceLanguage,
            $targetLanguage,
            $sourceSentence,
            $sourceContextSentencesString,
            $targetSentence,
            $targetContextSentencesString,
            $excerpt,
            $styleInstructions,
            $geminiResponseText
        );

        $this->assertEquals("", $result[0]['highlighted']['before']);
        $this->assertEquals("Spiderman", $result[0]['highlighted']['changed']);
        $this->assertEquals("è l'eroe americano... ", $result[0]['highlighted']['after']);
    }

    /**
     * Mocks the Gemini response for managing alternative translations by creating
     * mock objects for the client and generative model dependencies, and returning
     * the processed response via the `GeminiClient`'s `manageAlternativeTranslations` method.
     *
     * @param string $sourceLanguage The ISO language code of the source language (e.g., "en-US").
     * @param string $targetLanguage The ISO language code of the target language (e.g., "it-IT").
     * @param string $sourceSentence The main sentence in the source language.
     * @param string $sourceContextSentencesString Context sentences supporting the source sentence.
     * @param string $targetSentence The main sentence translated into the target language.
     * @param string $targetContextSentencesString Context sentences supporting the target sentence.
     * @param string $excerpt A specific excerpt from the source sentence selected by the user.
     * @param string $styleInstructions Instructions defining the style or tone of the translation.
     * @param string $geminiResponseText JSON-encoded Gemini response text used in the mock.
     *
     * @return array The mocked response structure representing alternative translations and metadata.
     */
    private function mockedGeminiResponse(
        $sourceLanguage,
        $targetLanguage,
        $sourceSentence,
        $sourceContextSentencesString,
        $targetSentence,
        $targetContextSentencesString,
        $excerpt,
        $styleInstructions,
        $geminiResponseText
    )
    {
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

        return $client->manageAlternativeTranslations(
            $sourceLanguage,
            $targetLanguage,
            $sourceSentence,
            $sourceContextSentencesString,
            $targetSentence,
            $targetContextSentencesString,
            $excerpt,
            $styleInstructions
        );
    }
}
