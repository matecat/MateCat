<?php

declare(strict_types=1);

namespace Tests\Unit\FeaturesBase;

use Model\FeaturesBase\BasicFeatureStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BasicFeatureStructTest extends TestCase
{
    #[Test]
    public function featureCodePropertyIsAccessible(): void
    {
        $struct = new BasicFeatureStruct();
        $struct->feature_code = 'translation_versions';

        self::assertSame('translation_versions', $struct->feature_code);
    }

    #[Test]
    public function optionsCanBeArray(): void
    {
        $struct = new BasicFeatureStruct();
        $struct->options = ['key' => 'value'];

        self::assertSame(['key' => 'value'], $struct->options);
    }

    #[Test]
    public function optionsCanBeString(): void
    {
        $struct = new BasicFeatureStruct();
        $struct->options = '{"key":"value"}';

        self::assertSame('{"key":"value"}', $struct->options);
    }

    #[Test]
    public function optionsCanBeNull(): void
    {
        $struct = new BasicFeatureStruct();
        $struct->options = null;

        self::assertNull($struct->options);
    }

    #[Test]
    public function getFullyQualifiedClassNameReturnsUnknownFeatureForInvalidCode(): void
    {
        $struct = new BasicFeatureStruct();
        $struct->feature_code = 'completely_nonexistent_feature_xyz';

        $result = $struct->getFullyQualifiedClassName();

        self::assertSame(\Plugins\Features\UnknownFeature::class, $result);
    }

    #[Test]
    public function toNewObjectReturnsUnknownFeatureInstanceForInvalidCode(): void
    {
        $struct = new BasicFeatureStruct();
        $struct->feature_code = 'completely_nonexistent_feature_xyz';
        $struct->options = null;

        $result = $struct->toNewObject();

        self::assertInstanceOf(\Plugins\Features\UnknownFeature::class, $result);
    }
}
