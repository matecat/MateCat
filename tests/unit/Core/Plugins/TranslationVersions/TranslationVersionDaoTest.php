<?php

namespace Matecat\Core\Plugins\TranslationVersions;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use Model\Translations\SegmentTranslationStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use Plugins\Features\TranslationVersions\Model\TranslationVersionStruct;
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
        $this->database->getConnection()->exec(
            "DELETE FROM segment_translation_versions WHERE id_job = " . self::JOB_ID . " AND id_segment BETWEEN 900000 AND 900100"
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
    public function insertVersionInsertsRecord(): void
    {
        $dao = new TranslationVersionDao();
        $dao->insertVersion($this->makeStruct(self::SEGMENT_ID_1, 1, 'Inserted text'));

        $rows = $this->database->getConnection()
            ->query("SELECT * FROM segment_translation_versions WHERE id_job = " . self::JOB_ID . " AND id_segment = " . self::SEGMENT_ID_1)
            ->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(1, $rows);
        $this->assertEquals(self::SEGMENT_ID_1, (int)$rows[0]['id_segment']);
        $this->assertEquals(1, (int)$rows[0]['version_number']);
        $this->assertEquals('Inserted text', $rows[0]['translation']);
        $this->assertEquals(1500, (int)$rows[0]['time_to_edit']);
    }

    #[Test]
    public function insertVersionDoesNotDeduplicateOnSameKey(): void
    {
        $dao = new TranslationVersionDao();
        $dao->insertVersion($this->makeStruct(self::SEGMENT_ID_1, 1, 'First write'));
        $dao->insertVersion($this->makeStruct(self::SEGMENT_ID_1, 1, 'Second write'));

        $rows = $this->database->getConnection()
            ->query("SELECT * FROM segment_translation_versions WHERE id_job = " . self::JOB_ID . " AND id_segment = " . self::SEGMENT_ID_1 . " AND version_number = 1")
            ->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(2, $rows, 'segment_translation_versions has no unique key on (id_job, id_segment, version_number), so inserting the same version twice must produce two rows, not an update');
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

    // --- Instance method tests (Step 1 — specular + new) ---

    #[Test]
    public function instanceGetVersionsForJobReturnsAllVersions(): void
    {
        $dao = new TranslationVersionDao();
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 1, 'First'));
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 2, 'Second'));
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_2, 1, 'Other segment'));

        $results = $dao->getVersionsForJob(self::JOB_ID);

        $this->assertCount(3, $results);
        $this->assertContainsOnlyInstancesOf(TranslationVersionStruct::class, $results);
    }

    #[Test]
    public function instanceGetVersionsForJobReturnsEmptyForNonexistentJob(): void
    {
        $dao = new TranslationVersionDao();

        $results = $dao->getVersionsForJob(0);

        $this->assertSame([], $results);
    }

    #[Test]
    public function instanceGetVersionsForChunkReturnsAllVersions(): void
    {
        $dao = new TranslationVersionDao();
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 1, 'First'));
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_2, 1, 'Second'));

        $chunk = new JobStruct();
        $chunk->id = self::JOB_ID;

        $results = $dao->getVersionsForChunk($chunk);

        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(TranslationVersionStruct::class, $results);
    }

    #[Test]
    public function instanceGetVersionsForChunkReturnsEmptyForNonexistentJob(): void
    {
        $dao = new TranslationVersionDao();

        $chunk = new JobStruct();
        $chunk->id = 0;

        $results = $dao->getVersionsForChunk($chunk);

        $this->assertSame([], $results);
    }

    #[Test]
    public function instanceGetVersionsForTranslationFiltersCorrectly(): void
    {
        $dao = new TranslationVersionDao();
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 1, 'V1'));
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 2, 'V2'));
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_2, 1, 'Other'));

        $results = $dao->getVersionsForTranslation(self::JOB_ID, self::SEGMENT_ID_1);

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertEquals(self::SEGMENT_ID_1, $result->id_segment);
        }
    }

    #[Test]
    public function instanceGetVersionsForTranslationFiltersByVersionNumber(): void
    {
        $dao = new TranslationVersionDao();
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 1, 'V1'));
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 2, 'V2'));

        $results = $dao->getVersionsForTranslation(self::JOB_ID, self::SEGMENT_ID_1, 2);

        $this->assertCount(1, $results);
        $this->assertEquals(2, $results[0]->version_number);
        $this->assertEquals('V2', $results[0]->translation);
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

    #[Test]
    public function savePropagationVersionsInsertsBatchRecords(): void
    {
        $propagator = new SegmentTranslationStruct();
        $propagator->id_job = self::JOB_ID;
        $propagator->id_segment = self::SEGMENT_ID_1;
        $propagator->autopropagated_from = self::SEGMENT_ID_1;

        $seg1 = new SegmentTranslationStruct();
        $seg1->id_job = self::JOB_ID;
        $seg1->id_segment = self::SEGMENT_ID_2;
        $seg1->translation = 'Propagated text';
        $seg1->version_number = 1;

        $jobStruct = new JobStruct();
        $jobStruct->id = self::JOB_ID;

        $dao = new TranslationVersionDao();
        $dao->savePropagationVersions($propagator, self::SEGMENT_ID_1, $jobStruct, [$seg1]);

        $rows = $this->database->getConnection()
            ->query("SELECT * FROM segment_translation_versions WHERE id_job = " . self::JOB_ID . " AND id_segment = " . self::SEGMENT_ID_2)
            ->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(1, $rows);
        $this->assertEquals('Propagated text', $rows[0]['translation']);
        $this->assertEquals(self::SEGMENT_ID_1, (int)$rows[0]['propagated_from']);
    }

    #[Test]
    public function savePropagationVersionsChunksLargeArrays(): void
    {
        $propagator = new SegmentTranslationStruct();
        $propagator->id_job = self::JOB_ID;
        $propagator->id_segment = self::SEGMENT_ID_1;
        $propagator->autopropagated_from = self::SEGMENT_ID_1;

        $jobStruct = new JobStruct();
        $jobStruct->id = self::JOB_ID;

        $segments = [];
        for ($i = 0; $i < 25; $i++) {
            $seg = new SegmentTranslationStruct();
            $seg->id_job = self::JOB_ID;
            $seg->id_segment = 900000 + $i;
            $seg->translation = "Propagated $i";
            $seg->version_number = 1;
            $segments[] = $seg;
        }

        $dao = new TranslationVersionDao();
        $dao->savePropagationVersions($propagator, self::SEGMENT_ID_1, $jobStruct, $segments);

        $count = $this->database->getConnection()
            ->query("SELECT COUNT(*) FROM segment_translation_versions WHERE id_job = " . self::JOB_ID . " AND propagated_from = " . self::SEGMENT_ID_1)
            ->fetchColumn();

        $this->assertEquals(25, (int)$count);
    }

    #[Test]
    public function getVersionsForRevisionReturnsEmptyForNonexistentData(): void
    {
        $dao = new TranslationVersionDao();

        $results = $dao->getVersionsForRevision(0, 0);

        $this->assertSame([], $results);
    }

    #[Test]
    public function getVersionsForRevisionReturnsVersionRecords(): void
    {
        $dao = new TranslationVersionDao();
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 1, 'Version 1'));
        $dao->saveVersion($this->makeStruct(self::SEGMENT_ID_1, 2, 'Version 2'));

        $results = $dao->getVersionsForRevision(self::JOB_ID, self::SEGMENT_ID_1);

        $this->assertNotEmpty($results);
        $this->assertContainsOnlyInstancesOf(ShapelessConcreteStruct::class, $results);
    }

}
