<?php

declare(strict_types=1);

namespace Matecat\Core\FeaturesBase;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\BaseFeature;
use ReflectionProperty;

#[Group('unit')]
class FeatureSetDatabaseInjectionTest extends AbstractTest
{
    #[Test]
    public function toNewObjectStampsDatabaseOnFeature(): void
    {
        $db = $this->createStub(IDatabase::class);

        $struct = new BasicFeatureStruct();
        $struct->feature_code = 'review_extended';
        $struct->options = null;

        $feature = $struct->toNewObject($db);

        self::assertSame($db, $this->readProtected($feature, 'database'));
    }

    #[Test]
    public function featureSetExposesInjectedDatabase(): void
    {
        $db = $this->createStub(IDatabase::class);

        $featureSet = new FeatureSet($db);

        self::assertSame($db, $featureSet->getDatabase());
    }

    #[Test]
    public function featureSetThreadsDatabaseThroughDispatch(): void
    {
        $db = $this->createStub(IDatabase::class);

        $featureSet = new FeatureSet($db);
        $featureSet->loadFromString('review_extended');

        $structs = $featureSet->getFeaturesStructs();
        self::assertNotEmpty($structs);

        foreach ($structs as $struct) {
            $feature = $struct->toNewObject($db);
            self::assertSame($db, $this->readProtected($feature, 'database'));
        }
    }

    private function readProtected(BaseFeature $feature, string $property): mixed
    {
        $ref = new ReflectionProperty(BaseFeature::class, $property);

        return $ref->getValue($feature);
    }
}
