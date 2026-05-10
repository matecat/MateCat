<?php

namespace unit\Model\Segments;

use Model\DataAccess\Database;
use Model\Segments\SegmentDisabledService;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

#[CoversClass(SegmentDisabledService::class)]
#[Group('PersistenceNeeded')]
class SegmentDisabledServiceTest extends AbstractTest
{
    private const int TEST_SEGMENT_ID   = 999888;
    private const int TEST_SEGMENT_ID_2 = 999887;

    private Database $database;
    private SegmentDisabledService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = Database::obtain(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );
        $this->service = new SegmentDisabledService();
        $this->cleanFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanFixtures();

        $flusher = new \Predis\Client(AppConfig::$REDIS_SERVERS);
        $flusher->select(AppConfig::$INSTANCE_ID);
        $flusher->flushdb();

        parent::tearDown();
    }

    private function cleanFixtures(): void
    {
        $ids = implode(',', [self::TEST_SEGMENT_ID, self::TEST_SEGMENT_ID_2]);
        $this->database->getConnection()->exec(
            "DELETE FROM segment_metadata WHERE id_segment IN ($ids)"
        );
    }

    private function insertDisabledFlag(int $idSegment, string $value = '1'): void
    {
        $this->database->getConnection()->prepare(
            "INSERT INTO segment_metadata (id_segment, meta_key, meta_value) VALUES (?, ?, ?)"
        )->execute([$idSegment, 'translation_disabled', $value]);
    }

    private function fetchDisabledRows(int $idSegment): array
    {
        $key = 'translation_disabled';

        return $this->database->getConnection()
            ->query("SELECT * FROM segment_metadata WHERE id_segment = $idSegment AND meta_key = '$key'")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- isDisabled() ---

    #[Test]
    public function isDisabledReturnsFalseWhenNoMetadataExists(): void
    {
        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
    }

    #[Test]
    public function isDisabledReturnsTrueWhenTranslationDisabledFlagIsSet(): void
    {
        $this->insertDisabledFlag(self::TEST_SEGMENT_ID);

        $this->assertTrue($this->service->isDisabled(self::TEST_SEGMENT_ID));
    }

    #[Test]
    public function isDisabledReturnsFalseWhenMetaValueIsNotOne(): void
    {
        $this->insertDisabledFlag(self::TEST_SEGMENT_ID, '0');

        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
    }

    #[Test]
    public function isDisabledReturnsFalseForDifferentMetaKey(): void
    {
        $this->database->getConnection()->prepare(
            "INSERT INTO segment_metadata (id_segment, meta_key, meta_value) VALUES (?, ?, ?)"
        )->execute([self::TEST_SEGMENT_ID, 'some_other_key', '1']);

        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
    }

    // --- disable() ---

    #[Test]
    public function disableCreatesTranslationDisabledRow(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);

        $rows = $this->fetchDisabledRows(self::TEST_SEGMENT_ID);
        $this->assertCount(1, $rows);
        $this->assertSame('1', $rows[0]['meta_value']);
    }

    #[Test]
    public function disableDoesNotAffectOtherSegments(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);

        $this->assertCount(0, $this->fetchDisabledRows(self::TEST_SEGMENT_ID_2));
    }

    // --- enable() ---

    #[Test]
    public function enableRemovesTranslationDisabledRow(): void
    {
        $this->insertDisabledFlag(self::TEST_SEGMENT_ID);

        $this->service->enable(self::TEST_SEGMENT_ID);

        $this->assertCount(0, $this->fetchDisabledRows(self::TEST_SEGMENT_ID));
    }

    #[Test]
    public function enableOnNonDisabledSegmentDoesNotThrow(): void
    {
        $this->service->enable(self::TEST_SEGMENT_ID);
        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
    }

    // --- Composition tests ---

    #[Test]
    public function disableThenIsDisabledReturnsTrue(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);

        $this->assertTrue($this->service->isDisabled(self::TEST_SEGMENT_ID));
    }

    #[Test]
    public function disableThenEnableThenIsDisabledReturnsFalse(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);
        $this->service->enable(self::TEST_SEGMENT_ID);

        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
    }

    #[Test]
    public function enableDoesNotAffectOtherDisabledSegments(): void
    {
        $this->service->disable(self::TEST_SEGMENT_ID);
        $this->service->disable(self::TEST_SEGMENT_ID_2);

        $this->service->enable(self::TEST_SEGMENT_ID);

        $this->assertFalse($this->service->isDisabled(self::TEST_SEGMENT_ID));
        $this->assertTrue($this->service->isDisabled(self::TEST_SEGMENT_ID_2));
    }
}
