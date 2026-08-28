<?php

namespace Matecat\Core\Model\Files;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Files\MetadataDao;
use Model\Files\MetadataStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TypeError;

/**
 * Cache-eviction coverage for Model\Files\MetadataDao against the live file_metadata table.
 *
 * A row here answers three reads, and two of them differ only in whether files_parts_id is bound —
 * two distinct addresses for the same row. These tests pin that one write clears both, whichever
 * side it was made from.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class MetadataDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const array TABLE_DEPS = ['file_metadata'];

    private MetadataDao $dao;
    private int $idProject;
    private int $idFile;
    private int $filePartsId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new MetadataDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);

        // file_metadata has no FK; an arbitrary (id_project, id_file) pair scopes the rows.
        $this->idProject   = $this->fixtures->nextAssignableId();
        $this->idFile      = $this->fixtures->nextAssignableId();
        $this->filePartsId = $this->fixtures->nextAssignableId();
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    /** Register a row the DAO INSERTed so cleanup returns to baseline. */
    private function track(string $key): void
    {
        $this->fixtures->trackExisting('file_metadata', [
            'id_project' => $this->idProject,
            'id_file'    => $this->idFile,
            'key'        => $key,
        ]);
    }

    /**
     * The row is written once with a files_parts_id and read back both ways. FilesInfoUtility does
     * exactly this: it reads the instructions bound to the file part, at a 300s TTL, and writes them
     * back without naming the part — the UPDATE has no files_parts_id clause, so it reaches the same
     * row through a different address.
     */
    #[Test]
    public function testUpdateEvictsTheAddressThatNamesTheFilePart(): void
    {
        $this->flushDaoCache();
        $this->dao->insert($this->idProject, $this->idFile, 'instructions', 'v1', $this->filePartsId);
        $this->track('instructions');

        self::assertSame(
            'v1',
            $this->dao->get($this->idProject, $this->idFile, 'instructions', $this->filePartsId, 3600)?->value
        );

        $this->dao->update($this->idProject, $this->idFile, 'instructions', 'v2');

        self::assertSame(
            'v2',
            $this->dao->get($this->idProject, $this->idFile, 'instructions', $this->filePartsId, 3600)?->value
        );
    }

    /** The mirror: a write naming the part has to clear the address that does not name it. */
    #[Test]
    public function testUpdateNamingTheFilePartEvictsThePartLessAddress(): void
    {
        $this->flushDaoCache();
        $this->dao->insert($this->idProject, $this->idFile, 'instructions', 'v1', $this->filePartsId);
        $this->track('instructions');

        self::assertSame('v1', $this->dao->get($this->idProject, $this->idFile, 'instructions', null, 3600)?->value);

        $this->dao->update($this->idProject, $this->idFile, 'instructions', 'v2', $this->filePartsId);

        self::assertSame('v2', $this->dao->get($this->idProject, $this->idFile, 'instructions', null, 3600)?->value);
    }

    #[Test]
    public function testDestroyCacheEvictsTheReadsAMetadataRowAddresses(): void
    {
        $this->flushDaoCache();
        $this->dao->insert($this->idProject, $this->idFile, 'context_url', 'u1', $this->filePartsId);
        $this->track('context_url');

        $this->dao->get($this->idProject, $this->idFile, 'context_url', null, 3600);
        $this->dao->get($this->idProject, $this->idFile, 'context_url', $this->filePartsId, 3600);
        $this->dao->getByJobIdProjectAndIdFile($this->idProject, $this->idFile, 3600);

        $this->writeBehindTheCache('context_url', 'u2');
        // the entries are warm, which is what makes the assertions below mean anything
        self::assertSame('u1', $this->dao->get($this->idProject, $this->idFile, 'context_url', null, 3600)?->value);

        $this->dao->destroyCache(new MetadataStruct([
            'id_project'     => $this->idProject,
            'id_file'        => $this->idFile,
            'key'            => 'context_url',
            'files_parts_id' => $this->filePartsId,
        ]));

        self::assertSame('u2', $this->dao->get($this->idProject, $this->idFile, 'context_url', null, 3600)?->value);
        self::assertSame(
            'u2',
            $this->dao->get($this->idProject, $this->idFile, 'context_url', $this->filePartsId, 3600)?->value
        );
        $rows = $this->dao->getByJobIdProjectAndIdFile($this->idProject, $this->idFile, 3600);
        self::assertSame('u2', $rows[0]->value);
    }

    /** Without this the test above would also pass on a door that cleared the whole DAO. */
    #[Test]
    public function testDestroyCacheLeavesAKeyItWasNotGiven(): void
    {
        $this->flushDaoCache();
        $this->dao->insert($this->idProject, $this->idFile, 'context_url', 'u1');
        $this->track('context_url');
        self::assertSame('u1', $this->dao->get($this->idProject, $this->idFile, 'context_url', null, 3600)?->value);

        $this->writeBehindTheCache('context_url', 'u2');

        $this->dao->destroyCache(new MetadataStruct([
            'id_project' => $this->idProject,
            'id_file'    => $this->idFile,
            'key'        => 'unrelated',
        ]));

        self::assertSame('u1', $this->dao->get($this->idProject, $this->idFile, 'context_url', null, 3600)?->value);
    }

    #[Test]
    public function testDestroyCacheRefusesAStructThatNamesNoKey(): void
    {
        $this->expectException(TypeError::class);

        $this->dao->destroyCache(new MetadataStruct([
            'id_project' => $this->idProject,
            'id_file'    => $this->idFile,
        ]));
    }

    /** Change the stored value without going through the DAO, so any entry left behind shows up. */
    private function writeBehindTheCache(string $key, string $value): void
    {
        $this->realSqlDb()->getConnection()
            ->prepare('UPDATE file_metadata SET value = :v WHERE id_project = :p AND id_file = :f AND `key` = :k')
            ->execute(['v' => $value, 'p' => $this->idProject, 'f' => $this->idFile, 'k' => $key]);
    }
}
