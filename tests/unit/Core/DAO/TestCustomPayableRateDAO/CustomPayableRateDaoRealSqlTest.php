<?php

namespace Matecat\Core\DAO\TestCustomPayableRateDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Analysis\PayableRates;
use Model\PayableRates\CustomPayableRateDao;
use Model\PayableRates\CustomPayableRateStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Real-SQL characterization tests for CustomPayableRateDao (campaign dao-realsql-90, T16).
 *
 * Each public SQL method (getDefaultTemplate, getAllPaginated, findById, getByIdAndUser, save,
 * update, remove, createFromJSON, editFromJSON, assocModelToJob) is called DIRECTLY against the
 * real unittest DB and asserted on round-tripped data (DoD b). The pre-existing mock test in this
 * directory is kept.
 *
 * Test rows live under an assignable uid >= ASSIGNABLE_ID_FLOOR (M-2). The pivot table
 * job_custom_payable_rates is keyed by an assignable id_job >= the floor. Cleanup is id/uid
 * scoped; the residue gate asserts whole-table COUNT(*) is unchanged for both tables (DoD c).
 * Generated ids are AUTO_INCREMENT — no assertion on absolute id values (M-3).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class CustomPayableRateDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private CustomPayableRateDao $dao;
    private int $uid;
    private int $idJob;

    protected function realSqlTableDeps(): array
    {
        return ['payable_rate_templates', 'job_custom_payable_rates'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();
        $this->uid = self::ASSIGNABLE_ID_FLOOR + 7090;
        $this->idJob = self::ASSIGNABLE_ID_FLOOR + 90;
        $this->dao = new CustomPayableRateDao($this->realSqlDb);
    }

    protected function tearDown(): void
    {
        $this->realSqlTearDown(function (): void {
            $conn = $this->realSqlDb->getConnection();
            $conn->exec("DELETE FROM payable_rate_templates WHERE uid = {$this->uid}");
            $conn->exec("DELETE FROM job_custom_payable_rates WHERE id_job = {$this->idJob}");
        });
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function breakdowns(): array
    {
        return ['default' => PayableRates::$DEFAULT_PAYABLE_RATES];
    }

    private function newStruct(string $name = 'rate'): CustomPayableRateStruct
    {
        $s = new CustomPayableRateStruct();
        $s->uid = $this->uid;
        $s->name = $name;
        $s->breakdowns = $this->breakdowns();

        return $s;
    }

    #[Test]
    public function dao_uses_the_injected_connection_not_the_singleton(): void
    {
        $this->assertSame($this->realSqlDb, $this->dao->getDatabaseHandler());
    }

    #[Test]
    public function getDefaultTemplate_returns_in_memory_default(): void
    {
        $default = $this->dao->getDefaultTemplate($this->uid);

        $this->assertSame(0, $default['id']);
        $this->assertSame($this->uid, $default['uid']);
        $this->assertSame('Matecat original settings', $default['payable_rate_template_name']);
        $this->assertSame(1, $default['version']);
        $this->assertArrayHasKey('default', $default['breakdowns']);
    }

    #[Test]
    public function save_inserts_and_returns_struct_with_generated_id(): void
    {
        $saved = $this->dao->save($this->newStruct('save-me'));

        $this->assertInstanceOf(CustomPayableRateStruct::class, $saved);
        $this->assertNotNull($saved->id);
        $this->assertGreaterThan(0, $saved->id);
        $this->assertSame(1, $saved->version);
        $this->assertNotNull($saved->created_at);

        $count = (int)$this->realSqlDb->getConnection()
            ->query("SELECT COUNT(*) FROM payable_rate_templates WHERE id = {$saved->id}")
            ->fetchColumn();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function findById_round_trips_the_row(): void
    {
        $saved = $this->dao->save($this->newStruct('find-me'));

        $found = $this->dao->findById($saved->id, 0);
        $this->assertInstanceOf(CustomPayableRateStruct::class, $found);
        $this->assertSame($saved->id, $found->id);
        $this->assertSame('find-me', $found->name);

        $this->assertNull($this->dao->findById(self::ASSIGNABLE_ID_FLOOR + 1, 0));
    }

    #[Test]
    public function getByIdAndUser_matches_only_the_owning_user(): void
    {
        $saved = $this->dao->save($this->newStruct('owned'));

        $found = $this->dao->getByIdAndUser($saved->id, $this->uid, 0);
        $this->assertInstanceOf(CustomPayableRateStruct::class, $found);
        $this->assertSame($saved->id, $found->id);

        $this->assertNull($this->dao->getByIdAndUser($saved->id, $this->uid + 1, 0));
    }

    #[Test]
    public function getAllPaginated_returns_paginated_rows(): void
    {
        $this->dao->save($this->newStruct('page-a'));
        $this->dao->save($this->newStruct('page-b'));

        $result = $this->dao->getAllPaginated($this->uid, '/base/route/', 1, 20, 0);

        $this->assertArrayHasKey('items', $result);
        $this->assertSame(1, $result['current_page']);
        $this->assertCount(2, $result['items']);
    }

    #[Test]
    public function update_bumps_version_and_persists_name(): void
    {
        $saved = $this->dao->save($this->newStruct('before'));
        $saved->name = 'after';

        $updated = $this->dao->update($saved);
        $this->assertSame('after', $updated->name);

        $row = $this->realSqlDb->getConnection()
            ->query("SELECT name, version FROM payable_rate_templates WHERE id = {$saved->id}")
            ->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('after', $row['name']);
        $this->assertSame(2, (int)$row['version']); // version was 1, update writes version+1
    }

    #[Test]
    public function remove_soft_deletes_and_hides_the_row(): void
    {
        $saved = $this->dao->save($this->newStruct('remove-me'));

        $affected = $this->dao->remove($saved->id, $this->uid);
        $this->assertSame(1, $affected);

        $this->assertNull($this->dao->findById($saved->id, 0));

        $row = $this->realSqlDb->getConnection()
            ->query("SELECT name, deleted_at FROM payable_rate_templates WHERE id = {$saved->id}")
            ->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($row['deleted_at']);
        $this->assertStringStartsWith('deleted_', $row['name']);

        $this->assertSame(0, $this->dao->remove($saved->id, $this->uid));
    }

    #[Test]
    public function createFromJSON_persists_a_new_row(): void
    {
        $json = json_encode([
            'payable_rate_template_name' => 'from-json',
            'breakdowns' => $this->breakdowns(),
        ]);

        $created = $this->dao->createFromJSON($json, $this->uid);

        $this->assertInstanceOf(CustomPayableRateStruct::class, $created);
        $this->assertNotNull($created->id);
        $this->assertSame('from-json', $created->name);
        $this->assertSame($this->uid, $created->uid);

        $count = (int)$this->realSqlDb->getConnection()
            ->query("SELECT COUNT(*) FROM payable_rate_templates WHERE id = {$created->id}")
            ->fetchColumn();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function editFromJSON_updates_an_existing_row(): void
    {
        $saved = $this->dao->save($this->newStruct('pre-edit'));

        $json = json_encode([
            'payable_rate_template_name' => 'post-edit',
            'breakdowns' => $this->breakdowns(),
        ]);

        $edited = $this->dao->editFromJSON($saved, $json);
        $this->assertSame('post-edit', $edited->name);

        $name = (string)$this->realSqlDb->getConnection()
            ->query("SELECT name FROM payable_rate_templates WHERE id = {$saved->id}")
            ->fetchColumn();
        $this->assertSame('post-edit', $name);
    }

    #[Test]
    public function assocModelToJob_inserts_into_the_pivot_table(): void
    {
        $saved = $this->dao->save($this->newStruct('assoc-me'));

        $insertId = $this->dao->assocModelToJob($saved->id, $this->idJob, 1, 'assoc-me');
        $this->assertIsString($insertId);

        $row = $this->realSqlDb->getConnection()
            ->query(
                "SELECT custom_payable_rate_model_id, custom_payable_rate_model_name, custom_payable_rate_model_version "
                . "FROM job_custom_payable_rates WHERE id_job = {$this->idJob}"
            )
            ->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame($saved->id, (int)$row['custom_payable_rate_model_id']);
        $this->assertSame('assoc-me', $row['custom_payable_rate_model_name']);
        $this->assertSame(1, (int)$row['custom_payable_rate_model_version']);
    }
}
