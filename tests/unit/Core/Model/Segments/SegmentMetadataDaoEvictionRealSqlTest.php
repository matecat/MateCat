<?php

namespace Matecat\Core\Model\Segments;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentMetadataStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TypeError;

/**
 * Cache-eviction coverage for Model\Segments\SegmentMetadataDao against the live segment_metadata
 * table.
 *
 * One row is read at four addresses — by segment, by segment and key, by a key across a set of
 * segments, and across a segment range — with TTLs of one to seven days. These tests pin that one
 * write clears all four, so a caller cannot be left compensating for the ones the DAO forgot.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class SegmentMetadataDaoEvictionRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const array TABLE_DEPS = ['segment_metadata'];

    private const string KEY = 'translation_disabled';

    private SegmentMetadataDao $dao;
    private int $idSegment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new SegmentMetadataDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);

        // segment_metadata has no FK; an arbitrary id_segment scopes the rows.
        $this->idSegment = $this->fixtures->nextAssignableId();
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    /**
     * The defect: `delete()` removed the row and evicted nothing, so all four reads kept serving it
     * — `get()` for seven days. It worked only because `SegmentDisabledService::enable()` made the
     * four eviction calls itself, which is the arrangement one forgotten call breaks.
     */
    #[Test]
    public function testDeleteEvictsEveryAddressTheRowWasReadAt(): void
    {
        $this->fixtures->makeSegmentMetadata($this->idSegment, self::KEY, '1');

        self::assertSame('1', $this->dao->get($this->idSegment, self::KEY)?->meta_value);
        self::assertCount(1, iterator_to_array($this->dao->getAll($this->idSegment)));
        self::assertCount(1, $this->dao->getBySegmentIds([$this->idSegment], self::KEY));
        self::assertCount(1, $this->dao->getAllInRange($this->idSegment, $this->idSegment));

        $this->dao->delete($this->idSegment, self::KEY);

        self::assertNull($this->dao->get($this->idSegment, self::KEY));
        self::assertCount(0, iterator_to_array($this->dao->getAll($this->idSegment)));
        self::assertCount(0, $this->dao->getBySegmentIds([$this->idSegment], self::KEY));
        self::assertCount(0, $this->dao->getAllInRange($this->idSegment, $this->idSegment));
    }

    #[Test]
    public function testDestroyCacheEvictsEveryAddressOfAMetadataRow(): void
    {
        $this->fixtures->makeSegmentMetadata($this->idSegment, self::KEY, '1');

        self::assertSame('1', $this->dao->get($this->idSegment, self::KEY)?->meta_value);
        self::assertSame('1', iterator_to_array($this->dao->getAll($this->idSegment))[0]->meta_value);
        self::assertSame('1', $this->dao->getBySegmentIds([$this->idSegment], self::KEY)[0]->meta_value);
        self::assertSame('1', iterator_to_array($this->dao->getAllInRange($this->idSegment, $this->idSegment)[$this->idSegment])[0]->meta_value);

        $this->editBehindTheCache('0');

        $this->dao->destroyCache($this->structFor(self::KEY));

        self::assertSame('0', $this->dao->get($this->idSegment, self::KEY)?->meta_value);
        self::assertSame('0', iterator_to_array($this->dao->getAll($this->idSegment))[0]->meta_value);
        self::assertSame('0', $this->dao->getBySegmentIds([$this->idSegment], self::KEY)[0]->meta_value);
        self::assertSame('0', iterator_to_array($this->dao->getAllInRange($this->idSegment, $this->idSegment)[$this->idSegment])[0]->meta_value);
    }

    /** Without this the test above would also pass on a door that cleared the whole DAO. */
    #[Test]
    public function testDestroyCacheLeavesTheKeyedReadOfAKeyItWasNotGiven(): void
    {
        $this->fixtures->makeSegmentMetadata($this->idSegment, self::KEY, '1');

        self::assertSame('1', $this->dao->get($this->idSegment, self::KEY)?->meta_value);

        $this->editBehindTheCache('0');

        $this->dao->destroyCache($this->structFor('unrelated'));

        self::assertSame('1', $this->dao->get($this->idSegment, self::KEY)?->meta_value);
    }

    #[Test]
    public function testDestroyCacheRefusesAStructThatNamesNoSegment(): void
    {
        $this->expectException(TypeError::class);

        $struct = new SegmentMetadataStruct();
        $struct->meta_key = self::KEY;

        $this->dao->destroyCache($struct);
    }

    #[Test]
    public function testDestroyCacheRefusesAStructThatNamesNoKey(): void
    {
        $this->expectException(TypeError::class);

        $struct = new SegmentMetadataStruct();
        $struct->id_segment = $this->idSegment;

        $this->dao->destroyCache($struct);
    }

    private function structFor(string $key): SegmentMetadataStruct
    {
        $struct = new SegmentMetadataStruct();
        $struct->id_segment = $this->idSegment;
        $struct->meta_key = $key;

        return $struct;
    }

    /** Change the stored value without going through the DAO, so any entry left behind shows up. */
    private function editBehindTheCache(string $value): void
    {
        $this->realSqlDb()->getConnection()
            ->prepare('UPDATE segment_metadata SET meta_value = :v WHERE id_segment = :s AND meta_key = :k')
            ->execute(['v' => $value, 's' => $this->idSegment, 'k' => self::KEY]);
    }
}
