<?php

namespace Matecat\Core\DAO\TestContextGroupDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Projects\ProjectStruct;
use Model\Segments\ContextGroupDao;
use Model\Segments\ContextStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Real-SQL coverage for ContextGroupDao (campaign dao-realsql-90).
 *
 * All four read methods (getAllByProject, getBySegmentID, getByFileID, getBySIDRange) run against
 * the real unittest DB. context_groups rows are inserted directly under an assignable id_project
 * (>= ASSIGNABLE_ID_FLOOR) and cleaned id_project-scoped; the residue gate asserts whole-table
 * COUNT(*) is unchanged (DoD c).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class ContextGroupDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private ContextGroupDao $dao;
    private int $idProject;
    private int $idFile;
    private int $sidA;
    private int $sidB;

    protected function realSqlTableDeps(): array
    {
        return ['context_groups'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();

        $this->idProject = self::ASSIGNABLE_ID_FLOOR + 3201;
        $this->idFile = self::ASSIGNABLE_ID_FLOOR + 3202;
        $this->sidA = self::ASSIGNABLE_ID_FLOOR + 3210;
        $this->sidB = self::ASSIGNABLE_ID_FLOOR + 3211;

        $this->insertRow($this->sidA);
        $this->insertRow($this->sidB);

        $this->dao = new ContextGroupDao($this->realSqlDb);
    }

    protected function tearDown(): void
    {
        $this->realSqlTearDown(function (): void {
            $this->realSqlDb->getConnection()
                ->exec("DELETE FROM context_groups WHERE id_project = {$this->idProject}");
        });
        parent::tearDown();
    }

    private function insertRow(int $idSegment): void
    {
        $stmt = $this->realSqlDb->getConnection()->prepare(
            "INSERT INTO context_groups (id_project, id_segment, id_file, context_json) "
            . "VALUES (:p, :s, :f, :j)"
        );
        $stmt->execute([
            'p' => $this->idProject,
            's' => $idSegment,
            'f' => $this->idFile,
            'j' => '{"ctx":"rsq"}',
        ]);
    }

    #[Test]
    public function dao_uses_the_injected_connection_not_the_singleton(): void
    {
        $this->assertInjectedConnection($this->dao);
    }

    #[Test]
    public function getAllByProject_returns_every_row_for_the_project(): void
    {
        $rows = $this->dao->getAllByProject(new ProjectStruct(['id' => $this->idProject]));

        $this->assertIsArray($rows);
        $this->assertCount(2, $rows);
        $this->assertInstanceOf(ProjectStruct::class, $rows[0]);
    }

    #[Test]
    public function getBySegmentID_returns_the_matching_row_or_null(): void
    {
        $row = $this->dao->getBySegmentID($this->sidA);
        $this->assertInstanceOf(ContextStruct::class, $row);
        $this->assertSame($this->sidA, (int)$row->id_segment);

        $this->assertNull($this->dao->getBySegmentID(self::ASSIGNABLE_ID_FLOOR + 99999));
    }

    #[Test]
    public function getByFileID_returns_all_rows_for_the_file(): void
    {
        $rows = $this->dao->getByFileID($this->idFile);

        $this->assertCount(2, $rows);
        $this->assertContainsOnlyInstancesOf(ContextStruct::class, $rows);

        $this->assertCount(0, $this->dao->getByFileID(self::ASSIGNABLE_ID_FLOOR + 99999));
    }

    #[Test]
    public function getBySIDRange_returns_rows_keyed_by_segment_id(): void
    {
        $rows = $this->dao->getBySIDRange($this->sidA, $this->sidB);

        $this->assertCount(2, $rows);
        $this->assertArrayHasKey($this->sidA, $rows);
        $this->assertArrayHasKey($this->sidB, $rows);
        $this->assertInstanceOf(ContextStruct::class, $rows[$this->sidA]);
    }
}
