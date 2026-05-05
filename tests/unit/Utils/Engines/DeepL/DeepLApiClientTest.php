<?php

namespace unit\Utils\Engines\DeepL;

use DateTime;
use DeepL\DeepLException;
use DeepL\GlossaryEntries;
use DeepL\GlossaryInfo;
use DeepL\TextResult;
use DeepL\Translator;
use TestHelpers\AbstractTest;
use Utils\Engines\DeepL\DeepLApiClient;
use Utils\Engines\DeepL\DeepLApiException;

/**
 * @covers \Utils\Engines\DeepL\DeepLApiClient
 */
class DeepLApiClientTest extends AbstractTest
{
    private Translator $translatorMock;
    private DeepLApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translatorMock = $this->createStub(Translator::class);
        $this->client = DeepLApiClient::newInstanceWithTranslator($this->translatorMock);
    }

    // ─── translate ───────────────────────────────────────────────────────────────

    public function test_translate_returns_expected_structure(): void
    {
        $textResult = $this->createStub(TextResult::class);
        $textResult->text = 'Ciao mondo';
        $textResult->detectedSourceLang = 'EN';

        $this->translatorMock
            ->method('translateText')
            ->willReturn($textResult);

        $result = $this->client->translate('Hello world', 'en', 'it');

        $this->assertSame([
            'translations' => [
                [
                    'detected_source_language' => 'EN',
                    'text' => 'Ciao mondo',
                ]
            ]
        ], $result);
    }

    public function test_translate_passes_formality_option(): void
    {
        $textResult = $this->createStub(TextResult::class);
        $textResult->text = 'Hallo Welt';
        $textResult->detectedSourceLang = 'EN';

        $mock = $this->createMock(Translator::class);
        $mock->expects($this->once())
            ->method('translateText')
            ->with('Hello world', 'en', 'de', ['formality' => 'more'])
            ->willReturn($textResult);

        $client = DeepLApiClient::newInstanceWithTranslator($mock);
        $client->translate('Hello world', 'en', 'de', 'more');
    }

    public function test_translate_passes_glossary_option(): void
    {
        $textResult = $this->createStub(TextResult::class);
        $textResult->text = 'Ciao mondo';
        $textResult->detectedSourceLang = 'EN';

        $mock = $this->createMock(Translator::class);
        $mock->expects($this->once())
            ->method('translateText')
            ->with('Hello world', 'en', 'it', ['glossary' => 'glossary-id-123'])
            ->willReturn($textResult);

        $client = DeepLApiClient::newInstanceWithTranslator($mock);
        $client->translate('Hello world', 'en', 'it', null, 'glossary-id-123');
    }

    public function test_translate_throws_DeepLApiException_on_failure(): void
    {
        $this->translatorMock
            ->method('translateText')
            ->willThrowException(new DeepLException('Quota exceeded'));

        $this->expectException(DeepLApiException::class);
        $this->expectExceptionMessage('Quota exceeded');

        $this->client->translate('Hello', 'en', 'it');
    }

    // ─── allGlossaries ───────────────────────────────────────────────────────────

    public function test_allGlossaries_returns_glossary_list(): void
    {
        $glossary1 = new GlossaryInfo(
            'id-1',
            'Test Glossary',
            true,
            'en',
            'it',
            new DateTime('2026-01-15T10:00:00Z'),
            5
        );

        $glossary2 = new GlossaryInfo(
            'id-2',
            'Another Glossary',
            false,
            'en',
            'de',
            new DateTime('2026-02-20T12:30:00Z'),
            10
        );

        $this->translatorMock
            ->method('listGlossaries')
            ->willReturn([$glossary1, $glossary2]);

        $result = $this->client->allGlossaries();

        $this->assertArrayHasKey('glossaries', $result);
        $this->assertCount(2, $result['glossaries']);
        $this->assertSame('id-1', $result['glossaries'][0]['glossary_id']);
        $this->assertSame('Test Glossary', $result['glossaries'][0]['name']);
        $this->assertTrue($result['glossaries'][0]['ready']);
        $this->assertSame('en', $result['glossaries'][0]['source_lang']);
        $this->assertSame('it', $result['glossaries'][0]['target_lang']);
        $this->assertSame(5, $result['glossaries'][0]['entry_count']);
        $this->assertSame('id-2', $result['glossaries'][1]['glossary_id']);
    }

    public function test_allGlossaries_returns_empty_list(): void
    {
        $this->translatorMock
            ->method('listGlossaries')
            ->willReturn([]);

        $result = $this->client->allGlossaries();

        $this->assertSame(['glossaries' => []], $result);
    }

    public function test_allGlossaries_throws_DeepLApiException_on_failure(): void
    {
        $this->translatorMock
            ->method('listGlossaries')
            ->willThrowException(new DeepLException('Unauthorized'));

        $this->expectException(DeepLApiException::class);
        $this->expectExceptionMessage('Unauthorized');

        $this->client->allGlossaries();
    }

    // ─── createGlossary ──────────────────────────────────────────────────────────

    public function test_createGlossary_with_csv_format(): void
    {
        $glossaryInfo = new GlossaryInfo(
            'new-id',
            'My Glossary',
            true,
            'en',
            'de',
            new DateTime('2026-04-29T10:00:00Z'),
            3
        );

        $mock = $this->createMock(Translator::class);
        $mock->expects($this->once())
            ->method('createGlossaryFromCsv')
            ->with('My Glossary', 'en', 'de', "Hello,Hallo\nWorld,Welt\nCat,Katze")
            ->willReturn($glossaryInfo);

        $client = DeepLApiClient::newInstanceWithTranslator($mock);
        $result = $client->createGlossary([
            'name' => 'My Glossary',
            'source_lang' => 'en',
            'target_lang' => 'de',
            'entries' => [['Hello', 'Hallo'], ['World', 'Welt'], ['Cat', 'Katze']],
            'entries_format' => 'csv',
        ]);

        $this->assertSame('new-id', $result['glossary_id']);
        $this->assertSame('My Glossary', $result['name']);
        $this->assertTrue($result['ready']);
        $this->assertSame(3, $result['entry_count']);
    }

    public function test_createGlossary_with_tsv_string(): void
    {
        $glossaryInfo = new GlossaryInfo(
            'tsv-id',
            'TSV Glossary',
            true,
            'en',
            'fr',
            new DateTime('2026-04-29T11:00:00Z'),
            2
        );

        $mock = $this->createMock(Translator::class);
        $mock->expects($this->once())
            ->method('createGlossary')
            ->with(
                'TSV Glossary',
                'en',
                'fr',
                $this->isInstanceOf(GlossaryEntries::class)
            )
            ->willReturn($glossaryInfo);

        $client = DeepLApiClient::newInstanceWithTranslator($mock);
        $result = $client->createGlossary([
            'name' => 'TSV Glossary',
            'source_lang' => 'en',
            'target_lang' => 'fr',
            'entries' => "Hello\tBonjour\nWorld\tMonde",
            'entries_format' => 'tsv',
        ]);

        $this->assertSame('tsv-id', $result['glossary_id']);
        $this->assertSame(2, $result['entry_count']);
    }

    public function test_createGlossary_throws_DeepLApiException_on_failure(): void
    {
        $this->translatorMock
            ->method('createGlossaryFromCsv')
            ->willThrowException(new DeepLException('Bad request'));

        $this->expectException(DeepLApiException::class);
        $this->expectExceptionMessage('Bad request');

        $this->client->createGlossary([
            'name' => 'Fail',
            'source_lang' => 'en',
            'target_lang' => 'de',
            'entries' => [['a', 'b']],
            'entries_format' => 'csv',
        ]);
    }

    // ─── deleteGlossary ──────────────────────────────────────────────────────────

    public function test_deleteGlossary_returns_id(): void
    {
        $mock = $this->createMock(Translator::class);
        $mock->expects($this->once())
            ->method('deleteGlossary')
            ->with('glossary-to-delete');

        $client = DeepLApiClient::newInstanceWithTranslator($mock);
        $result = $client->deleteGlossary('glossary-to-delete');

        $this->assertSame(['id' => 'glossary-to-delete'], $result);
    }

    public function test_deleteGlossary_throws_DeepLApiException_on_failure(): void
    {
        $this->translatorMock
            ->method('deleteGlossary')
            ->willThrowException(new DeepLException('Not found'));

        $this->expectException(DeepLApiException::class);
        $this->expectExceptionMessage('Not found');

        $this->client->deleteGlossary('non-existent');
    }

    // ─── getGlossary ─────────────────────────────────────────────────────────────

    public function test_getGlossary_returns_glossary_info(): void
    {
        $glossaryInfo = new GlossaryInfo(
            'existing-id',
            'Existing Glossary',
            true,
            'en',
            'it',
            new DateTime('2026-03-10T08:00:00Z'),
            7
        );

        $this->translatorMock
            ->method('getGlossary')
            ->willReturn($glossaryInfo);

        $result = $this->client->getGlossary('existing-id');

        $this->assertSame('existing-id', $result['glossary_id']);
        $this->assertSame('Existing Glossary', $result['name']);
        $this->assertTrue($result['ready']);
        $this->assertSame('en', $result['source_lang']);
        $this->assertSame('it', $result['target_lang']);
        $this->assertSame(7, $result['entry_count']);
        $this->assertArrayHasKey('creation_time', $result);
    }

    public function test_getGlossary_throws_DeepLApiException_on_failure(): void
    {
        $this->translatorMock
            ->method('getGlossary')
            ->willThrowException(new DeepLException('Glossary not found'));

        $this->expectException(DeepLApiException::class);
        $this->expectExceptionMessage('Glossary not found');

        $this->client->getGlossary('invalid-id');
    }

    // ─── getGlossaryEntries ──────────────────────────────────────────────────────

    public function test_getGlossaryEntries_returns_entries_array(): void
    {
        $entries = GlossaryEntries::fromTsv("Hello\tCiao\nWorld\tMondo");

        $this->translatorMock
            ->method('getGlossaryEntries')
            ->willReturn($entries);

        $result = $this->client->getGlossaryEntries('entries-id');

        $this->assertSame(['Hello' => 'Ciao', 'World' => 'Mondo'], $result);
    }

    public function test_getGlossaryEntries_throws_DeepLApiException_on_failure(): void
    {
        $this->translatorMock
            ->method('getGlossaryEntries')
            ->willThrowException(new DeepLException('Forbidden'));

        $this->expectException(DeepLApiException::class);
        $this->expectExceptionMessage('Forbidden');

        $this->client->getGlossaryEntries('forbidden-id');
    }
}

