<?php

namespace Matecat\Core\DAO\TestEngineDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\EngineConstants;

/**
 * Real-SQL characterization tests for EngineDAO (campaign dao-realsql-90, T16).
 *
 * Each public SQL method (create, read, destroyCache, updateByStruct, delete, disable, enable,
 * sanitize, validateForUser) is called DIRECTLY against the real unittest DB and asserted on the
 * round-tripped data (DoD b). The pre-existing mock/CRUD tests in this directory are kept.
 *
 * The `engines` table is pre-seeded with 5 protected rows (ids 0,1,2,9927877,9927878). All test
 * rows are created under an assignable uid >= ASSIGNABLE_ID_FLOOR (1.9e9), strictly above every
 * seeded uid (max seeded uid = 1886428310), so cleanup-by-uid never deletes a seeded row (M-1).
 * Engine ids are AUTO_INCREMENT (M-2 autoincrement strategy): rows are tracked + deleted by uid;
 * no assertion is made on absolute generated id values (M-3).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class EngineDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private EngineDAO $dao;
    private int $uid;

    protected function realSqlTableDeps(): array
    {
        return ['engines'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();
        $this->uid = self::ASSIGNABLE_ID_FLOOR + 7016;
        $this->dao = new EngineDAO($this->realSqlDb);
    }

    protected function tearDown(): void
    {
        $this->realSqlTearDown(function (): void {
            $this->realSqlDb->getConnection()->exec(
                "DELETE FROM engines WHERE uid = {$this->uid}"
            );
        });
        parent::tearDown();
    }

    private function newEngineStruct(string $name = 'T16 Engine', ?int $active = 1): EngineStruct
    {
        $s = new EngineStruct();
        $s->name = $name;
        $s->type = EngineConstants::MT;
        $s->description = 'real-sql test engine';
        $s->base_url = 'https://example.invalid/api';
        $s->translate_relative_url = 'get';
        $s->others = ['k' => 'v'];
        $s->extra_parameters = ['p' => 1];
        $s->class_load = 'MyMemory';
        $s->penalty = 14;
        $s->active = $active === null ? null : (bool)$active;
        $s->uid = $this->uid;

        return $s;
    }

    #[Test]
    public function dao_uses_the_injected_connection_not_the_singleton(): void
    {
        $this->assertSame($this->realSqlDb, $this->dao->getDatabaseHandler());
    }

    #[Test]
    public function create_inserts_and_returns_struct_with_generated_id(): void
    {
        $created = $this->dao->create($this->newEngineStruct('create-me'));

        $this->assertInstanceOf(EngineStruct::class, $created);
        $this->assertNotNull($created->id);
        $this->assertGreaterThan(0, $created->id); // generated id is positive (no absolute-value assertion, M-3)

        // Verify the row really landed in the DB with the expected user-visible fields.
        $row = $this->realSqlDb->getConnection()
            ->query("SELECT name, type, base_url, uid FROM engines WHERE id = {$created->id}")
            ->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('create-me', $row['name']);
        $this->assertSame(EngineConstants::MT, $row['type']);
        $this->assertSame($this->uid, (int)$row['uid']);

        // create() decodes its JSON fields back to arrays on the returned struct.
        $this->assertIsArray($created->others);
        $this->assertIsArray($created->extra_parameters);
    }

    #[Test]
    public function read_returns_matching_engine_structs(): void
    {
        $created = $this->dao->create($this->newEngineStruct('read-me'));

        $probe = new EngineStruct();
        $probe->id = $created->id;
        $probe->uid = $this->uid;

        $results = $this->dao->read($probe);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(EngineStruct::class, $results[0]);
        $this->assertSame($created->id, $results[0]->id);
        $this->assertSame('read-me', $results[0]->name);
    }

    #[Test]
    public function read_returns_empty_for_anonymous_user(): void
    {
        // _buildQueryForEngine throws DomainException for uid <= 0 -> read() returns [].
        $probe = new EngineStruct();
        $probe->uid = 0;
        $probe->id = 123;

        $this->assertSame([], $this->dao->read($probe));
    }

    #[Test]
    public function destroyCache_runs_against_the_db_and_short_circuits_for_anonymous(): void
    {
        $created = $this->dao->create($this->newEngineStruct('cache-me'));

        $probe = new EngineStruct();
        $probe->id = $created->id;
        $probe->uid = $this->uid;

        // _destroyObjectCache reports whether a cache entry was actually removed; with a freshly
        // flushed cache nothing is cached for this query, so it characteristically returns false.
        $this->assertFalse($this->dao->destroyCache($probe));

        // Populate the cache via a cached read, then destroyCache removes it -> true.
        $this->dao->setCacheTTL(60)->read($probe);
        $this->assertTrue($this->dao->destroyCache($probe));

        // Anonymous request short-circuits to true without querying.
        $anon = new EngineStruct();
        $anon->uid = 0;
        $anon->id = 1;
        $this->assertTrue($this->dao->destroyCache($anon));
    }

    #[Test]
    public function updateByStruct_persists_changed_fields(): void
    {
        $created = $this->dao->create($this->newEngineStruct('before-update'));

        $update = new EngineStruct();
        $update->id = $created->id;
        $update->uid = $this->uid;
        $update->name = 'after-update';
        $update->active = true;
        $update->others = ['changed' => true];
        $update->extra_parameters = ['x' => 2];

        $affected = $this->dao->updateByStruct($update);
        $this->assertGreaterThanOrEqual(0, $affected);

        $row = $this->realSqlDb->getConnection()
            ->query("SELECT name FROM engines WHERE id = {$created->id}")
            ->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('after-update', $row['name']);
    }

    #[Test]
    public function disable_sets_active_to_zero_and_returns_struct(): void
    {
        $created = $this->dao->create($this->newEngineStruct('disable-me', 1));

        $result = $this->dao->disable($this->makePk($created->id));

        $this->assertInstanceOf(EngineStruct::class, $result);
        $this->assertFalse($result->active);

        $active = (int)$this->realSqlDb->getConnection()
            ->query("SELECT active FROM engines WHERE id = {$created->id}")
            ->fetchColumn();
        $this->assertSame(0, $active);
    }

    #[Test]
    public function disable_returns_null_when_no_row_matches(): void
    {
        // No engine with this id/uid -> rowCount 0 -> null.
        $this->assertNull($this->dao->disable($this->makePk(self::ASSIGNABLE_ID_FLOOR + 999)));
    }

    #[Test]
    public function enable_throws_because_of_named_vs_positional_bind_mismatch(): void
    {
        // Findings (prod bug, NOT fixed): EngineDAO::enable() (EngineDAO.php:288-294) prepares a
        // query with NAMED placeholders (:id, :uid) but executes with a POSITIONAL array
        // [id, uid]. PDO rejects this with SQLSTATE[HY093] "Invalid parameter number", so EVERY
        // call to enable() throws a PDOException — the method is entirely broken (it can never
        // re-activate an engine). Characterized here; left unfixed (no prod changes).
        $created = $this->dao->create($this->newEngineStruct('enable-me', 0));

        $this->expectException(\PDOException::class);
        $this->dao->enable($this->makePk($created->id));
    }

    #[Test]
    public function delete_removes_the_row_and_returns_struct_then_null(): void
    {
        $created = $this->dao->create($this->newEngineStruct('delete-me'));

        $deleted = $this->dao->delete($this->makePk($created->id));
        $this->assertInstanceOf(EngineStruct::class, $deleted);

        $count = (int)$this->realSqlDb->getConnection()
            ->query("SELECT COUNT(*) FROM engines WHERE id = {$created->id}")
            ->fetchColumn();
        $this->assertSame(0, $count);

        // Deleting again matches nothing -> null.
        $this->assertNull($this->dao->delete($this->makePk($created->id)));
    }

    #[Test]
    public function validateForUser_throws_only_when_an_active_engine_with_same_name_exists(): void
    {
        $struct = $this->newEngineStruct('dup-name', 1);
        $this->dao->create($struct);

        $probe = new EngineStruct();
        $probe->name = 'dup-name';
        $probe->uid = $this->uid;

        // An active duplicate exists -> throws.
        try {
            $this->dao->validateForUser($probe);
            $this->fail('Expected validateForUser to throw for an existing active engine name.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('dup-name', $e->getMessage());
        }

        // A name with no active engine -> no throw (assert by reaching the next line).
        $free = new EngineStruct();
        $free->name = 'free-name-' . uniqid();
        $free->uid = $this->uid;
        $this->dao->validateForUser($free);
        $this->assertTrue(true);
    }

    #[Test]
    public function sanitize_normalizes_json_and_empty_fields(): void
    {
        $struct = $this->newEngineStruct('sanitize-me');
        $struct->others = ['a' => 1];
        $struct->extra_parameters = [];

        $sanitized = $this->dao->sanitize($struct);

        $this->assertInstanceOf(EngineStruct::class, $sanitized);
        // Non-empty array -> JSON string; empty array -> '{}'.
        $this->assertSame(json_encode(['a' => 1]), $sanitized->others);
        $this->assertSame('{}', $sanitized->extra_parameters);
    }

    #[Test]
    public function read_applies_active_type_and_class_load_filters(): void
    {
        $this->dao->create($this->newEngineStruct('filtered', 1));

        // Exercises the active / type / class_load WHERE branches of _buildQueryForEngine.
        $probe = new EngineStruct();
        $probe->uid = $this->uid;
        $probe->active = true;
        $probe->type = EngineConstants::MT;
        $probe->class_load = 'MyMemory';

        $results = $this->dao->read($probe);

        $this->assertNotEmpty($results);
        foreach ($results as $engine) {
            $this->assertSame($this->uid, $engine->uid);
            $this->assertSame(EngineConstants::MT, $engine->type);
        }
    }

    #[Test]
    public function read_throws_when_no_where_condition_can_be_built(): void
    {
        // An all-null struct produces no WHERE conditions -> _buildQueryForEngine throws
        // "Where condition needed." (not a DomainException, so read() does not swallow it).
        $empty = new EngineStruct();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Where condition needed.');
        $this->dao->read($empty);
    }

    #[Test]
    public function read_maps_unknown_class_load_to_none_struct(): void
    {
        // Insert a row whose class_load is not resolvable by EnginesFactory; _buildResult
        // falls back to a NONEStruct for it.
        $conn = $this->realSqlDb->getConnection();
        $conn->prepare(
            "INSERT INTO engines (name, type, base_url, others, extra_parameters, class_load, penalty, active, uid) "
            . "VALUES (:name, :type, :base_url, '{}', '{}', :class_load, 14, 1, :uid)"
        )->execute([
            'name' => 'unknown-class',
            'type' => EngineConstants::MT,
            'base_url' => 'https://example.invalid/x',
            'class_load' => 'ThisClassDoesNotExist_T16',
            'uid' => $this->uid,
        ]);

        $probe = new EngineStruct();
        $probe->uid = $this->uid;
        $results = $this->dao->read($probe);

        $this->assertNotEmpty($results);
        $this->assertContainsOnlyInstancesOf(EngineStruct::class, $results);
        $hasNone = false;
        foreach ($results as $r) {
            if ($r instanceof \Model\Engines\Structs\NONEStruct) {
                $hasNone = true;
            }
        }
        $this->assertTrue($hasNone, 'Expected an unknown class_load row to map to a NONEStruct.');
    }

    #[Test]
    public function updateByStruct_throws_when_no_updatable_fields(): void
    {
        // active/name null and only the always-present others/extra_parameters would be set;
        // updateByStruct always pushes others+extra_parameters, so to hit the "empty" guard we
        // must validate the primary-key path first: a struct missing id triggers _validatePrimaryKey.
        $noPk = new EngineStruct();
        $noPk->name = 'x';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Engine ID required');
        $this->dao->updateByStruct($noPk);
    }

    #[Test]
    public function create_throws_when_base_url_is_null(): void
    {
        $struct = $this->newEngineStruct('no-url');
        $struct->base_url = null;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Base URL cannot be null');
        $this->dao->create($struct);
    }

    #[Test]
    public function create_throws_for_a_disallowed_type(): void
    {
        $struct = $this->newEngineStruct('bad-type');
        $struct->type = 'NOT_A_REAL_TYPE';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Type not allowed');
        $this->dao->create($struct);
    }

    #[Test]
    public function delete_throws_when_primary_key_uid_is_missing(): void
    {
        $struct = new EngineStruct();
        $struct->id = self::ASSIGNABLE_ID_FLOOR + 1; // id present, uid null -> validatePrimaryKey throws

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("User's uid required");
        $this->dao->delete($struct);
    }

    private function makePk(int $id): EngineStruct
    {
        $s = new EngineStruct();
        $s->id = $id;
        $s->uid = $this->uid;

        return $s;
    }
}
