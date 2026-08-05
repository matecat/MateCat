<?php

declare(strict_types=1);

namespace Matecat\Core\Engines\Results\MyMemory;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\Results\MyMemory\Matches;

#[Group('unit')]
class GetMemoryResponseTest extends AbstractTest
{
    /** @return array<string, mixed> */
    private function decodedWithOneMatch(): array
    {
        return [
            'responseStatus' => 200,
            'responseData'   => '',
            'matches'        => [
                [
                    'id'               => '1',
                    'segment'          => 'Hello world',
                    'translation'      => 'Ciao mondo',
                    'match'            => 0.95,
                    'last-update-date' => '2024-01-01 00:00:00',
                    'create-date'      => '2024-01-01 00:00:00',
                    'tm_properties'    => '',
                ],
            ],
        ];
    }

    #[Test]
    public function getInstancePropagatesFeatureSetToEachMatch(): void
    {
        $db         = $this->createStub(IDatabase::class);
        $featureSet = new FeatureSet($db);

        $response = GetMemoryResponse::getInstance($this->decodedWithOneMatch(), $featureSet);

        self::assertNotEmpty($response->matches);

        $ref = new ReflectionProperty(Matches::class, 'featureSet');
        foreach ($response->matches as $match) {
            self::assertSame(
                $featureSet,
                $ref->getValue($match),
                'each Matches built by the response must carry the response featureSet'
            );
        }
    }

    #[Test]
    public function getMatchesAsArrayReturnsOneEntryPerMatch(): void
    {
        $db         = $this->createStub(IDatabase::class);
        $featureSet = new FeatureSet($db);

        $response = GetMemoryResponse::getInstance($this->decodedWithOneMatch(), $featureSet);
        $array    = $response->get_matches_as_array();

        self::assertCount(1, $array);
        self::assertSame('Ciao mondo', $array[0]['raw_translation']);
    }

    /**
     * Proves Bug 1 fix: when the MyMemory API returns a match entry where the
     * `segment` or `translation` key is absent entirely (e.g. MT-only entries,
     * degraded API responses), buildMyMemoryMatch must produce empty strings —
     * not null — so downstream callers are never handed a null content field.
     */
    #[Test]
    public function matchWithMissingSegmentAndTranslationKeysProducesEmptyStrings(): void
    {
        $db         = $this->createStub(IDatabase::class);
        $featureSet = new FeatureSet($db);

        $decoded = [
            'responseStatus' => 200,
            'responseData'   => '',
            'matches'        => [
                [
                    'id'               => '42',
                    // 'segment' and 'translation' intentionally absent to simulate API omitting them
                    'match'            => 0.80,
                    'last-update-date' => '2024-01-01 00:00:00',
                    'create-date'      => '2024-01-01 00:00:00',
                    'tm_properties'    => '',
                ],
            ],
        ];

        $response = GetMemoryResponse::getInstance($decoded, $featureSet);
        $array    = $response->get_matches_as_array(1);

        self::assertCount(1, $array);
        self::assertSame('', $array[0]['raw_segment'],    'missing segment key must produce empty string, not null');
        self::assertSame('', $array[0]['raw_translation'], 'missing translation key must produce empty string, not null');
        self::assertNotNull($array[0]['raw_segment'],    'raw_segment must never be null');
        self::assertNotNull($array[0]['raw_translation'], 'raw_translation must never be null');
    }

    /**
     * Proves Bug 2 fix: source and target language codes injected into decoded
     * match data (as _decode() now does from $this->_config) must be hydrated
     * on the Matches object and appear in get_matches_as_array() output.
     * Before the fix buildMyMemoryMatch() never passed these keys, leaving
     * source and target permanently null on every MyMemory TM match.
     */
    #[Test]
    public function matchWithSourceAndTargetLanguageCodesHydratesthem(): void
    {
        $db         = $this->createStub(IDatabase::class);
        $featureSet = new FeatureSet($db);

        $decoded = [
            'responseStatus' => 200,
            'responseData'   => '',
            'matches'        => [
                [
                    'id'               => '1',
                    'segment'          => 'Hello world',
                    'translation'      => 'Ciao mondo',
                    'match'            => 0.95,
                    'last-update-date' => '2024-01-01 00:00:00',
                    'create-date'      => '2024-01-01 00:00:00',
                    'tm_properties'    => '',
                    'source'           => 'en-US',
                    'target'           => 'it-IT',
                ],
            ],
        ];

        $response = GetMemoryResponse::getInstance($decoded, $featureSet);
        $array    = $response->get_matches_as_array(1);

        self::assertCount(1, $array);
        self::assertSame('en-US', $array[0]['source'], 'source language code must be hydrated on the match');
        self::assertSame('it-IT', $array[0]['target'], 'target language code must be hydrated on the match');
    }

    /**
     * Proves Bug 3 fix: when the tm_properties key is absent from the raw match
     * data, buildMyMemoryMatch must not raise a PHP notice and Matches must
     * default tm_properties to an empty array.
     */
    #[Test]
    public function matchWithMissingTmPropertiesDefaultsToEmptyArray(): void
    {
        $db         = $this->createStub(IDatabase::class);
        $featureSet = new FeatureSet($db);

        $decoded = [
            'responseStatus' => 200,
            'responseData'   => '',
            'matches'        => [
                [
                    'id'               => '1',
                    'segment'          => 'Hello world',
                    'translation'      => 'Ciao mondo',
                    'match'            => 0.95,
                    'last-update-date' => '2024-01-01 00:00:00',
                    'create-date'      => '2024-01-01 00:00:00',
                    // 'tm_properties' intentionally absent
                ],
            ],
        ];

        $response = GetMemoryResponse::getInstance($decoded, $featureSet);
        $array    = $response->get_matches_as_array(1);

        self::assertCount(1, $array);
        self::assertIsArray($array[0]['tm_properties'], 'missing tm_properties must default to an array');
        self::assertEmpty($array[0]['tm_properties']);
    }
}
