<?php

namespace Matecat\Core\DAO\TestMTQEPayableRateTemplateDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\MTQE\PayableRate\MTQEPayableRateStruct;
use Model\MTQE\PayableRate\MTQEPayableRateTemplateDao;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Real-SQL characterization tests for MTQEPayableRateTemplateDao (campaign dao-realsql-90, T16).
 *
 * Every public SQL method is called DIRECTLY against the real unittest DB and asserted on the
 * round-tripped data (DoD b). The pre-existing mock test in this directory is kept as-is.
 *
 * Test rows live under an assignable uid >= ASSIGNABLE_ID_FLOOR (M-2) so they never collide
 * with — or require deleting — any seeded row (M-1). Cleanup is a uid-scoped DELETE and the
 * residue gate asserts whole-table COUNT(*) is unchanged across the test.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class MTQEPayableRateTemplateDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private MTQEPayableRateTemplateDao $dao;
    private int $uid;

    protected function realSqlTableDeps(): array
    {
        return ['mt_qe_payable_rate_templates'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();
        $this->uid = self::ASSIGNABLE_ID_FLOOR + 7016;
        $this->dao = new MTQEPayableRateTemplateDao($this->realSqlDb);
    }

    protected function tearDown(): void
    {
        $this->realSqlTearDown(function (): void {
            $this->realSqlDb->getConnection()->exec(
                "DELETE FROM mt_qe_payable_rate_templates WHERE uid = {$this->uid}"
            );
        });
        parent::tearDown();
    }

    /** Insert a template row directly (not via the DAO, which has no insert method). */
    private function seedTemplate(string $name, ?string $deletedAt = null): int
    {
        $conn = $this->realSqlDb->getConnection();
        $stmt = $conn->prepare(
            "INSERT INTO mt_qe_payable_rate_templates (uid, name, breakdowns, created_at, deleted_at) "
            . "VALUES (:uid, :name, :breakdowns, NOW(), :deleted)"
        );
        $stmt->execute([
            'uid' => $this->uid,
            'name' => $name,
            'breakdowns' => json_encode(['breakdowns' => ['ICE' => 0, 'TM_100' => 0, 'MT' => 50]]),
            'deleted' => $deletedAt,
        ]);

        return (int)$conn->lastInsertId();
    }

    #[Test]
    public function dao_uses_the_injected_connection_not_the_singleton(): void
    {
        // C-2: identity of the DAO handler must be the single harness connection.
        $this->assertSame($this->realSqlDb, $this->dao->getDatabaseHandler());
    }

    #[Test]
    public function getById_returns_the_row(): void
    {
        $id = $this->seedTemplate('rate-by-id');

        $struct = $this->dao->getById($id, 0);

        $this->assertInstanceOf(MTQEPayableRateStruct::class, $struct);
        $this->assertSame($id, $struct->id);
        $this->assertSame($this->uid, $struct->uid);
        $this->assertSame('rate-by-id', $struct->name);
        $this->assertInstanceOf(\Model\MTQE\PayableRate\DTO\MTQEPayableRateBreakdowns::class, $struct->breakdowns);
    }

    #[Test]
    public function getById_returns_null_for_missing_and_for_soft_deleted(): void
    {
        $this->assertNull($this->dao->getById(self::ASSIGNABLE_ID_FLOOR + 1, 0));

        $deletedId = $this->seedTemplate('rate-deleted', '2020-01-01 00:00:00');
        $this->assertNull($this->dao->getById($deletedId, 0));
    }

    #[Test]
    public function getByIdAndUser_matches_only_the_owning_user(): void
    {
        $id = $this->seedTemplate('rate-by-id-uid');

        $struct = $this->dao->getByIdAndUser($id, $this->uid, 0);
        $this->assertInstanceOf(MTQEPayableRateStruct::class, $struct);
        $this->assertSame($id, $struct->id);

        $this->assertNull($this->dao->getByIdAndUser($id, $this->uid + 1, 0));
    }

    #[Test]
    public function getByUid_returns_all_live_rows_for_the_user(): void
    {
        $this->seedTemplate('rate-a');
        $this->seedTemplate('rate-b');
        $this->seedTemplate('rate-deleted-b', '2020-01-01 00:00:00');

        $rows = $this->dao->getByUid($this->uid, 0);

        $this->assertCount(2, $rows);
        $names = array_map(static fn(MTQEPayableRateStruct $s) => $s->name, $rows);
        sort($names);
        $this->assertSame(['rate-a', 'rate-b'], $names);

        $this->assertSame([], $this->dao->getByUid($this->uid + 999, 0));
    }

    #[Test]
    public function getAllPaginated_returns_paginated_live_rows(): void
    {
        $this->seedTemplate('page-a');
        $this->seedTemplate('page-b');

        $result = $this->dao->getAllPaginated($this->uid, '/base/route/', 1, 20, 0);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertSame(1, $result['current_page']);
        $this->assertCount(2, $result['items']);
        foreach ($result['items'] as $item) {
            $this->assertInstanceOf(MTQEPayableRateStruct::class, $item);
            $this->assertSame($this->uid, $item->uid);
        }
    }

    #[Test]
    public function remove_soft_deletes_and_hides_the_row(): void
    {
        $id = $this->seedTemplate('rate-to-remove');

        $affected = $this->dao->remove($id, $this->uid);
        $this->assertSame(1, $affected);

        // Soft-deleted: no longer visible through the DAO.
        $this->assertNull($this->dao->getById($id, 0));
        $this->assertNull($this->dao->getByIdAndUser($id, $this->uid, 0));

        // But the row physically remains (soft delete) — so cleanup still must remove it.
        $stillThere = (int)$this->realSqlDb->getConnection()
            ->query("SELECT COUNT(*) FROM mt_qe_payable_rate_templates WHERE id = {$id}")
            ->fetchColumn();
        $this->assertSame(1, $stillThere);

        // Removing an already-removed / non-owned row affects 0 rows.
        $this->assertSame(0, $this->dao->remove($id, $this->uid));
    }

    #[Test]
    public function getDefaultTemplate_builds_an_in_memory_struct_without_touching_the_db(): void
    {
        $struct = $this->dao->getDefaultTemplate($this->uid);

        $this->assertInstanceOf(MTQEPayableRateStruct::class, $struct);
        $this->assertSame(0, $struct->id);
        $this->assertSame($this->uid, $struct->uid);
        $this->assertSame('Matecat default settings', $struct->name);
    }
}
