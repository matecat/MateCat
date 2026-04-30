<?php

namespace unit\Plugins\TranslationVersions;

use Model\DataAccess\Database;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use Plugins\Features\TranslationVersions\Model\TranslationVersionStruct;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class TranslationVersionDaoTest extends AbstractTest
{
    private const int JOB_ID = 999901;
    private const int SEGMENT_ID_1 = 999801;
    private const int SEGMENT_ID_2 = 999802;

    private Database $database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::obtain(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );
        $this->deleteFixtureRows();
    }

    protected function tearDown(): void
    {
        $this->deleteFixtureRows();
        parent::tearDown();
    }

    private function deleteFixtureRows(): void
    {
        $this->database->getConnection()->exec(
            "DELETE FROM segment_translation_versions WHERE id_job = " . self::JOB_ID
        );
    }

    private function makeStruct(int $idSegment, int $versionNumber, ?string $translation = 'Test translation'): TranslationVersionStruct
    {
        $struct = new TranslationVersionStruct();
        $struct->id_job = self::JOB_ID;
        $struct->id_segment = $idSegment;
        $struct->version_number = $versionNumber;
        $struct->translation = $translation;
        $struct->time_to_edit = 1500;

        return $struct;
    }

    #[Test]
    public function saveVersionInsertsRecord(): void
    {
        $dao = new TranslationVersionDao();
        $result = $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 1));

        $this->assertTrue($result);

        $rows = $this->database->getConnection()
            ->query("SELECT * FROM segment_translation_versions WHERE id_job = " . self::JOB_ID . " AND id_segment = " . self::SEGMENT_ID_1)
            ->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(1, $rows);
        $this->assertEquals(self::SEGMENT_ID_1, (int)$rows[0]['id_segment']);
        $this->assertEquals(1, (int)$rows[0]['version_number']);
        $this->assertEquals('Test translation', $rows[0]['translation']);
        $this->assertEquals(1500, (int)$rows[0]['time_to_edit']);
    }

    #[Test]
    public function getVersionsForJobReturnsAllVersions(): void
    {
        $dao = new TranslationVersionDao();
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 1, 'First'));
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 2, 'Second'));
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_2, 1, 'Other segment'));

        $results = TranslationVersionDao::getVersionsForJob(self::JOB_ID);

        $this->assertCount(3, $results);
        $this->assertContainsOnlyInstancesOf(TranslationVersionStruct::class, $results);
    }

    #[Test]
    public function getVersionsForJobReturnsEmptyForNonexistentJob(): void
    {
        $results = TranslationVersionDao::getVersionsForJob(0);

        $this->assertSame([], $results);
    }

    #[Test]
    public function getVersionsForTranslationFiltersCorrectly(): void
    {
        $dao = new TranslationVersionDao();
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 1, 'V1'));
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 2, 'V2'));
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_2, 1, 'Other'));

        $results = TranslationVersionDao::getVersionsForTranslation(self::JOB_ID, self::SEGMENT_ID_1);

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertEquals(self::SEGMENT_ID_1, $result->id_segment);
        }
    }

    #[Test]
    public function getVersionsForTranslationFiltersByVersionNumber(): void
    {
        $dao = new TranslationVersionDao();
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 1, 'V1'));
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 2, 'V2'));

        $results = TranslationVersionDao::getVersionsForTranslation(self::JOB_ID, self::SEGMENT_ID_1, 2);

        $this->assertCount(1, $results);
        $this->assertEquals(2, $results[0]->version_number);
        $this->assertEquals('V2', $results[0]->translation);
    }

    #[Test]
    public function getVersionNumberForTranslationReturnsStruct(): void
    {
        $dao = new TranslationVersionDao();
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 3, 'Specific'));

        $result = $dao->getVersionNumberForTranslation(self::JOB_ID, self::SEGMENT_ID_1, 3);

        $this->assertInstanceOf(TranslationVersionStruct::class, $result);
        $this->assertEquals(3, $result->version_number);
        $this->assertEquals('Specific', $result->translation);
    }

    #[Test]
    public function getVersionNumberForTranslationReturnsFalseWhenNotFound(): void
    {
        $dao = new TranslationVersionDao();

        $result = $dao->getVersionNumberForTranslation(self::JOB_ID, self::SEGMENT_ID_1, 999);

        $this->assertFalse($result);
    }

    #[Test]
    public function updateVersionModifiesExistingRecord(): void
    {
        $dao = new TranslationVersionDao();
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 1, 'Original'));

        $updated = new TranslationVersionStruct();
        $updated->id_job = self::JOB_ID;
        $updated->id_segment = self::SEGMENT_ID_1;
        $updated->version_number = 1;
        $updated->translation = 'Updated text';
        $updated->time_to_edit = 3000;

        $rowCount = $dao->updateVersion($updated);

        $this->assertEquals(1, $rowCount);

        $result = $dao->getVersionNumberForTranslation(self::JOB_ID, self::SEGMENT_ID_1, 1);
        $this->assertEquals('Updated text', $result->translation);
        $this->assertEquals(3000, $result->time_to_edit);
    }

    #[Test]
    public function updateVersionReturnsZeroForNonexistentRecord(): void
    {
        $dao = new TranslationVersionDao();

        $struct = $this->makeStruct(self::SEGMENT_ID_1, 999, 'Ghost');
        $rowCount = $dao->updateVersion($struct);

        $this->assertEquals(0, $rowCount);
    }

    #[Test]
    public function saveVersionPreservesNullableFields(): void
    {
        $struct = new TranslationVersionStruct();
        $struct->id_job = self::JOB_ID;
        $struct->id_segment = self::SEGMENT_ID_1;
        $struct->version_number = 1;
        $struct->translation = null;
        $struct->time_to_edit = null;
        $struct->old_status = 3;
        $struct->new_status = 4;

        $dao = new TranslationVersionDao();
        $result = $dao->saveVersion($struct);

        $this->assertTrue($result);

        $fetched = $dao->getVersionNumberForTranslation(self::JOB_ID, self::SEGMENT_ID_1, 1);
        $this->assertNull($fetched->translation);
        $this->assertNull($fetched->time_to_edit);
    }
}
