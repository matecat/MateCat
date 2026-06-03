<?php

namespace Matecat\Core\Model\OwnerFeatures;

use Matecat\TestHelpers\AbstractTest;
use Model\OwnerFeatures\OwnerFeatureStruct;
use PHPUnit\Framework\Attributes\Test;

class OwnerFeatureStructTest extends AbstractTest
{
    #[Test]
    public function getOptionsDecodesJsonString(): void
    {
        $struct = new OwnerFeatureStruct();
        $struct->options = '{"key":"value","num":42}';

        $result = $struct->getOptions();

        $this->assertIsArray($result);
        $this->assertSame('value', $result['key']);
        $this->assertSame(42, $result['num']);
    }

    #[Test]
    public function getOptionsReturnsArrayDirectly(): void
    {
        $struct = new OwnerFeatureStruct();
        $struct->options = ['key' => 'value'];

        $result = $struct->getOptions();

        $this->assertIsArray($result);
        $this->assertSame('value', $result['key']);
    }

    #[Test]
    public function getOptionsReturnsNullForNullOptions(): void
    {
        $struct = new OwnerFeatureStruct();
        $struct->options = null;

        $this->assertNull($struct->getOptions());
    }

    #[Test]
    public function canSetProperties(): void
    {
        $struct = new OwnerFeatureStruct();
        $struct->id = 1;
        $struct->uid = 100;
        $struct->id_team = 5;
        $struct->feature_code = 'test_feature';
        $struct->enabled = true;
        $struct->last_update = '2026-01-01 00:00:00';
        $struct->create_date = '2026-01-01 00:00:00';

        $this->assertSame(1, $struct->id);
        $this->assertSame(100, $struct->uid);
        $this->assertSame(5, $struct->id_team);
        $this->assertSame('test_feature', $struct->feature_code);
        $this->assertTrue($struct->enabled);
    }

    #[Test]
    public function idTeamDefaultsToNull(): void
    {
        $struct = new OwnerFeatureStruct();

        $this->assertNull($struct->id_team);
        $this->assertNull($struct->last_update);
        $this->assertNull($struct->create_date);
    }
}
