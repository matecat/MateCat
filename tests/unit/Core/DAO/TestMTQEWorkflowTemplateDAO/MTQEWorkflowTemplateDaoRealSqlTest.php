<?php

namespace Matecat\Core\DAO\TestMTQEWorkflowTemplateDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\MTQE\Templates\MTQEWorkflowTemplateDao;
use Model\MTQE\Templates\MTQEWorkflowTemplateStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Real-SQL characterization tests for MTQEWorkflowTemplateDao (campaign dao-realsql-90, T16).
 *
 * Each public SQL method is called DIRECTLY against the real unittest DB and asserted on the
 * round-tripped data (DoD b). Test rows live under an assignable uid >= ASSIGNABLE_ID_FLOOR
 * (M-2). Cleanup is a uid-scoped DELETE; the residue gate asserts whole-table COUNT(*) is
 * unchanged across the test.
 *
 * Fixtures are seeded against the LIVE schema: `mt_qe_templates` stores the workflow params in
 * the `params` column (the checked-in unittest_matecat_local.sql still names it `rules`; the
 * live DB has migrated — see Findings).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class MTQEWorkflowTemplateDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private MTQEWorkflowTemplateDao $dao;
    private int $uid;

    protected function realSqlTableDeps(): array
    {
        return ['mt_qe_templates'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();
        $this->uid = self::ASSIGNABLE_ID_FLOOR + 7160;
        $this->dao = new MTQEWorkflowTemplateDao($this->realSqlDb);
    }

    protected function tearDown(): void
    {
        $this->realSqlTearDown(function (): void {
            $this->realSqlDb->getConnection()->exec(
                "DELETE FROM mt_qe_templates WHERE uid = {$this->uid}"
            );
        });
        parent::tearDown();
    }

    private function seedTemplate(string $name, ?string $deletedAt = null): int
    {
        $conn = $this->realSqlDb->getConnection();
        $stmt = $conn->prepare(
            "INSERT INTO mt_qe_templates (uid, name, params, created_at, deleted_at) "
            . "VALUES (:uid, :name, :params, NOW(), :deleted)"
        );
        $stmt->execute([
            'uid' => $this->uid,
            'name' => $name,
            'params' => json_encode(['rules' => ['threshold' => 80]]),
            'deleted' => $deletedAt,
        ]);

        return (int)$conn->lastInsertId();
    }

    #[Test]
    public function dao_uses_the_injected_connection_not_the_singleton(): void
    {
        $this->assertSame($this->realSqlDb, $this->dao->getDatabaseHandler());
    }

    #[Test]
    public function getById_round_trips_the_persisted_row(): void
    {
        $id = $this->seedTemplate('wf-by-id');

        $struct = $this->dao->getById($id, 0);

        $this->assertInstanceOf(MTQEWorkflowTemplateStruct::class, $struct);
        $this->assertSame($id, $struct->id);
        $this->assertSame($this->uid, $struct->uid);
        $this->assertSame('wf-by-id', $struct->name);
    }

    #[Test]
    public function getById_returns_null_for_missing_and_soft_deleted(): void
    {
        $this->assertNull($this->dao->getById(self::ASSIGNABLE_ID_FLOOR + 1, 0));

        $deletedId = $this->seedTemplate('wf-deleted', '2020-01-01 00:00:00');
        $this->assertNull($this->dao->getById($deletedId, 0));
    }

    #[Test]
    public function getByIdAndUser_matches_only_the_owning_user(): void
    {
        $id = $this->seedTemplate('wf-by-id-uid');

        $struct = $this->dao->getByIdAndUser($id, $this->uid, 0);
        $this->assertInstanceOf(MTQEWorkflowTemplateStruct::class, $struct);
        $this->assertSame($id, $struct->id);

        $this->assertNull($this->dao->getByIdAndUser($id, $this->uid + 1, 0));
    }

    #[Test]
    public function getByUid_returns_all_live_rows_for_the_user(): void
    {
        $this->seedTemplate('wf-a');
        $this->seedTemplate('wf-b');
        $this->seedTemplate('wf-deleted-b', '2020-01-01 00:00:00');

        $rows = $this->dao->getByUid($this->uid, 0);

        $this->assertCount(2, $rows);
        $names = array_map(static fn(MTQEWorkflowTemplateStruct $s) => $s->name, $rows);
        sort($names);
        $this->assertSame(['wf-a', 'wf-b'], $names);

        $this->assertSame([], $this->dao->getByUid($this->uid + 999, 0));
    }

    #[Test]
    public function getAllPaginated_returns_paginated_live_rows(): void
    {
        $this->seedTemplate('wf-page-a');
        $this->seedTemplate('wf-page-b');

        $result = $this->dao->getAllPaginated($this->uid, '/base/route/', 1, 20, 0);

        $this->assertArrayHasKey('items', $result);
        $this->assertSame(1, $result['current_page']);
        $this->assertCount(2, $result['items']);
        foreach ($result['items'] as $item) {
            $this->assertInstanceOf(MTQEWorkflowTemplateStruct::class, $item);
            $this->assertSame($this->uid, $item->uid);
        }
    }

    #[Test]
    public function remove_soft_deletes_and_renames_the_row(): void
    {
        $id = $this->seedTemplate('wf-to-remove');

        $affected = $this->dao->remove($id, $this->uid);
        $this->assertSame(1, $affected);

        $row = $this->realSqlDb->getConnection()
            ->query("SELECT name, deleted_at FROM mt_qe_templates WHERE id = {$id}")
            ->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($row['deleted_at']);
        $this->assertStringStartsWith('deleted_', $row['name']);

        // No longer visible through the DAO.
        $this->assertNull($this->dao->getById($id, 0));

        // Re-removing affects 0 rows (deleted_at no longer NULL).
        $this->assertSame(0, $this->dao->remove($id, $this->uid));
    }

    #[Test]
    public function getDefaultTemplate_builds_an_in_memory_struct(): void
    {
        $struct = $this->dao->getDefaultTemplate($this->uid);

        $this->assertInstanceOf(MTQEWorkflowTemplateStruct::class, $struct);
        $this->assertSame(0, $struct->id);
        $this->assertSame($this->uid, $struct->uid);
        $this->assertSame('Matecat default settings', $struct->name);
    }
}
