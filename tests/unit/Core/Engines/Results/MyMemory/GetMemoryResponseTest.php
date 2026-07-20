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
}
