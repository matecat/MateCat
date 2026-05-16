<?php

namespace unit\DAO\TestCustomPayableRateDAO;

use Exception;
use Model\PayableRates\CustomPayableRateDao;
use Model\PayableRates\CustomPayableRateStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class CustomPayableRateDaoTest extends AbstractTest
{
    #[Test]
    public function saveThrowsWhenUidIsNull(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->uid = null;
        $struct->name = 'test';
        $struct->breakdowns = ['default' => []];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('uid');

        CustomPayableRateDao::save($struct);
    }

    #[Test]
    public function updateThrowsWhenIdIsNull(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->id = null;
        $struct->uid = 1;
        $struct->name = 'test';
        $struct->version = 1;
        $struct->breakdowns = ['default' => []];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('id');

        CustomPayableRateDao::update($struct);
    }

    #[Test]
    public function updateThrowsWhenUidIsNull(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->id = 1;
        $struct->uid = null;
        $struct->name = 'test';
        $struct->version = 1;
        $struct->breakdowns = ['default' => []];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('uid');

        CustomPayableRateDao::update($struct);
    }

    #[Test]
    public function getDefaultTemplateReturnsValidStructure(): void
    {
        $result = CustomPayableRateDao::getDefaultTemplate(42);

        $this->assertIsArray($result);
        $this->assertSame(0, $result['id']);
        $this->assertSame(42, $result['uid']);
        $this->assertSame('Matecat original settings', $result['payable_rate_template_name']);
        $this->assertArrayHasKey('breakdowns', $result);
        $this->assertArrayHasKey('createdAt', $result);
        $this->assertArrayHasKey('modifiedAt', $result);
        $this->assertNotNull($result['createdAt']);
        $this->assertNotNull($result['modifiedAt']);
    }
}
