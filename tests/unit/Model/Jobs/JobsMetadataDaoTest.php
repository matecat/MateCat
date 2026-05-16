<?php

namespace unit\Model\Jobs;

use Model\DataAccess\Database;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use TestHelpers\AbstractTest;

#[Group('PersistenceNeeded')]
class JobsMetadataDaoTest extends AbstractTest
{
    private const int TEST_JOB_ID   = 999999;
    private const string TEST_PASSWORD = 'test_bulk_pwd';

    /**
     * @throws ReflectionException
     */
    private function resetJobMetadata(JobsMetadataDao $dao): void
    {
        $rows = $dao->getByJobIdAndPassword(self::TEST_JOB_ID, self::TEST_PASSWORD);
        foreach ($rows as $row) {
            $dao->delete(self::TEST_JOB_ID, self::TEST_PASSWORD, $row->key);
        }
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testBulkSetInsertsMultipleKeys(): void
    {
        $dao = new JobsMetadataDao(Database::obtain());
        $this->resetJobMetadata($dao);

        $dao->bulkSet(self::TEST_JOB_ID, self::TEST_PASSWORD, [
            'key_a' => 'val_a',
            'key_b' => 'val_b',
            'key_c' => 'val_c',
        ]);

        $recordA = $dao->get(self::TEST_JOB_ID, self::TEST_PASSWORD, 'key_a');
        $recordB = $dao->get(self::TEST_JOB_ID, self::TEST_PASSWORD, 'key_b');
        $recordC = $dao->get(self::TEST_JOB_ID, self::TEST_PASSWORD, 'key_c');

        $this->assertSame('val_a', $recordA?->value);
        $this->assertSame('val_b', $recordB?->value);
        $this->assertSame('val_c', $recordC?->value);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testBulkSetUpsertsExistingKeys(): void
    {
        $dao = new JobsMetadataDao(Database::obtain());
        $this->resetJobMetadata($dao);
        $dao->set(self::TEST_JOB_ID, self::TEST_PASSWORD, 'existing_key', 'old_value');

        $dao->bulkSet(self::TEST_JOB_ID, self::TEST_PASSWORD, [
            'existing_key' => 'new_value',
            'new_key'      => 'fresh',
        ]);

        $existing = $dao->get(self::TEST_JOB_ID, self::TEST_PASSWORD, 'existing_key');
        $new      = $dao->get(self::TEST_JOB_ID, self::TEST_PASSWORD, 'new_key');

        $this->assertSame('new_value', $existing?->value);
        $this->assertSame('fresh', $new?->value);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testBulkSetWithEmptyArrayIsNoop(): void
    {
        $dao = new JobsMetadataDao(Database::obtain());
        $this->resetJobMetadata($dao);
        $countBefore = count($dao->getByJobIdAndPassword(self::TEST_JOB_ID, self::TEST_PASSWORD));

        $dao->bulkSet(self::TEST_JOB_ID, self::TEST_PASSWORD, []);

        $countAfter = count($dao->getByJobIdAndPassword(self::TEST_JOB_ID, self::TEST_PASSWORD));
        $this->assertSame($countBefore, $countAfter);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testBulkSetWithSinglePair(): void
    {
        $dao = new JobsMetadataDao(Database::obtain());
        $this->resetJobMetadata($dao);

        $dao->bulkSet(self::TEST_JOB_ID, self::TEST_PASSWORD, [
            'solo_key' => 'solo_value',
        ]);

        $record = $dao->get(self::TEST_JOB_ID, self::TEST_PASSWORD, 'solo_key');
        $this->assertSame('solo_value', $record?->value);

        $allRows = $dao->getByJobIdAndPassword(self::TEST_JOB_ID, self::TEST_PASSWORD);
        $this->assertCount(1, $allRows);
    }
}
