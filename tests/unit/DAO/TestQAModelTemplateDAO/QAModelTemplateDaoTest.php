<?php

namespace unit\DAO\TestQAModelTemplateDAO;

use Exception;
use Model\LQA\QAModelTemplate\QAModelTemplateDao;
use Model\LQA\QAModelTemplate\QAModelTemplatePassfailStruct;
use Model\LQA\QAModelTemplate\QAModelTemplatePassfailThresholdStruct;
use Model\LQA\QAModelTemplate\QAModelTemplateStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class QAModelTemplateDaoTest extends AbstractTest
{
    #[Test]
    public function saveThrowsWhenPassfailIsNull(): void
    {
        $struct = new QAModelTemplateStruct();
        $struct->uid = 1;
        $struct->version = 1;
        $struct->label = 'Test';
        $struct->passfail = null;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('passfail');

        QAModelTemplateDao::save($struct);
    }

    #[Test]
    public function updateThrowsWhenPassfailIsNull(): void
    {
        $struct = new QAModelTemplateStruct();
        $struct->id = 999;
        $struct->uid = 1;
        $struct->version = 1;
        $struct->label = 'Test';
        $struct->passfail = null;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('passfail');

        QAModelTemplateDao::update($struct);
    }

    #[Test]
    public function getDefaultTemplateReturnsValidStructure(): void
    {
        $result = QAModelTemplateDao::getDefaultTemplate(42);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('uid', $result);
        $this->assertArrayHasKey('label', $result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('passfail', $result);
        $this->assertArrayHasKey('createdAt', $result);
        $this->assertArrayHasKey('modifiedAt', $result);

        $this->assertSame(0, $result['id']);
        $this->assertSame(42, $result['uid']);
        $this->assertSame('Matecat original settings', $result['label']);
    }

    #[Test]
    public function getDefaultTemplateReturnsIsoFormattedDates(): void
    {
        $result = QAModelTemplateDao::getDefaultTemplate(1);

        $this->assertNotNull($result['createdAt']);
        $this->assertNotNull($result['modifiedAt']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $result['createdAt']
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $result['modifiedAt']
        );
    }

    #[Test]
    public function getDefaultTemplateReturnsCategories(): void
    {
        $result = QAModelTemplateDao::getDefaultTemplate(1);

        $this->assertNotEmpty($result['categories']);
        $firstCategory = $result['categories'][0];
        $this->assertArrayHasKey('id', $firstCategory);
        $this->assertArrayHasKey('label', $firstCategory);
        $this->assertArrayHasKey('severities', $firstCategory);
        $this->assertNotEmpty($firstCategory['severities']);
    }

    #[Test]
    public function getDefaultTemplateReturnsPassfailWithThresholds(): void
    {
        $result = QAModelTemplateDao::getDefaultTemplate(1);

        $passfail = $result['passfail'];
        $this->assertArrayHasKey('id', $passfail);
        $this->assertArrayHasKey('thresholds', $passfail);
        $this->assertSame(0, $passfail['id']);
        $this->assertCount(2, $passfail['thresholds']);

        $threshold = $passfail['thresholds'][0];
        $this->assertArrayHasKey('label', $threshold);
        $this->assertArrayHasKey('value', $threshold);
    }

    #[Test]
    public function saveAcceptsStructWithValidPassfail(): void
    {
        // Verify save() does not throw when passfail is properly set
        // (it will throw PDOException from DB — that's expected, not Exception about passfail)
        $struct = new QAModelTemplateStruct();
        $struct->uid = 1;
        $struct->version = 1;
        $struct->label = 'Test';

        $passfail = new QAModelTemplatePassfailStruct();
        $passfail->passfail_type = 'points';
        $passfail->thresholds = [];

        $threshold = new QAModelTemplatePassfailThresholdStruct();
        $threshold->passfail_label = 'R1';
        $threshold->passfail_value = 5;
        $passfail->thresholds[] = $threshold;

        $struct->passfail = $passfail;
        $struct->categories = [];

        // If passfail guard passes, it will fail on DB connection — not on "passfail" exception
        try {
            QAModelTemplateDao::save($struct);
            $this->fail('Expected an exception (DB or otherwise)');
        } catch (Exception $e) {
            // Must NOT be the passfail null guard
            $this->assertStringNotContainsString('passfail', strtolower($e->getMessage()));
        }
    }
}
