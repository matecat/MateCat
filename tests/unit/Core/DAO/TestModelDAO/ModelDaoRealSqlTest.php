<?php

namespace Matecat\Core\DAO\TestModelDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\LQA\CategoryDao;
use Model\LQA\ModelDao;
use Model\LQA\ModelStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Real-SQL coverage for ModelDao (campaign dao-realsql-90).
 *
 * Every public method runs against the live unittest DB on the single per-test connection.
 * createRecord writes a qa_models row (both decodePassOptions arms + the id_template/label
 * ternaries); createModelFromJsonDefinition drives the recursive insertCategory across qa_categories
 * (default-vs-own severities, empty-vs-populated options, subcategory recursion). The residue gate
 * asserts whole-table COUNT(*) is unchanged; DAO-inserted rows are tracked for cleanup.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class ModelDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private ModelDao $dao;
    private int $uid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startRealSql(['qa_models', 'qa_categories']);

        $this->uid = $this->fixtures->makeUser()['uid'];
        $this->dao = new ModelDao($this->realSqlDb());
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    #[Test]
    public function dao_uses_the_injected_connection_not_the_singleton(): void
    {
        $this->assertDaoUsesTestConnection($this->dao);
    }

    #[Test]
    public function createRecord_with_limit_options_label_and_template(): void
    {
        $model = $this->dao->createRecord([
            'uid'         => $this->uid,
            'label'       => 'With Limit',
            'version'     => 1,
            'passfail'    => ['type' => 'pass_fail', 'options' => ['limit' => ['15', 10]]],
            'id_template' => self::ASSIGNABLE_ID_FLOOR + 777001,
        ]);
        $this->fixtures->trackExisting('qa_models', ['id' => $model->id]);

        $this->assertInstanceOf(ModelStruct::class, $model);
        $this->assertGreaterThan(0, $model->id);
        $this->assertSame($this->uid, $model->uid);
        $this->assertSame('With Limit', $model->label);
        $this->assertSame('pass_fail', $model->pass_type);
        // decodePassOptions cast each limit to int
        $this->assertSame('{"limit":[15,10]}', $model->pass_options);
        $this->assertSame(self::ASSIGNABLE_ID_FLOOR + 777001, (int)$model->qa_model_template_id);

        // round-trips through the DB on the same connection
        $reloaded = $this->dao->findById($model->id);
        $this->assertInstanceOf(ModelStruct::class, $reloaded);
        $this->assertSame($model->id, $reloaded->id);
    }

    #[Test]
    public function createRecord_without_limit_template_or_label(): void
    {
        // ModelStruct::$label is a non-null string, so createRecord always needs a label;
        // this case isolates the no-'limit' (json_encode([])) and no-id_template arms.
        $model = $this->dao->createRecord([
            'uid'      => $this->uid,
            'label'    => 'No Limit',
            'passfail' => ['type' => 'fixed', 'options' => []],
        ]);
        $this->fixtures->trackExisting('qa_models', ['id' => $model->id]);

        // no 'limit' key -> json_encode([])
        $this->assertSame('[]', $model->pass_options);
        // no id_template -> null
        $this->assertNull($model->qa_model_template_id);
    }

    #[Test]
    public function findById_miss_returns_null(): void
    {
        $this->assertNull($this->dao->findById(self::ASSIGNABLE_ID_FLOOR + 777999));
    }

    #[Test]
    public function createModelFromJsonDefinition_inserts_categories_subcategories_and_severities(): void
    {
        $json = [
            'model' => [
                'uid'        => $this->uid,
                'label'      => 'Full Model',
                'version'    => 2,
                'passfail'   => ['type' => 'pass_fail', 'options' => ['limit' => [20, 10]]],
                // default severities applied to any category that omits its own
                'severities' => [['penalty' => 1], ['penalty' => 5]],
                'categories' => [
                    [
                        // own severities (skip default), 'code' -> non-empty options, has subcategories (recursion)
                        'label'         => 'Accuracy',
                        'code'          => 'AC',
                        'severities'    => [['penalty' => 9]],
                        'subcategories' => [
                            // only label -> empty options (null arm); no severities -> default arm; no subcategories
                            ['label' => 'Mistranslation'],
                        ],
                    ],
                    [
                        // no severities -> default arm; 'code' -> non-empty options; no subcategories -> skip
                        'label' => 'Fluency',
                        'code'  => 'FL',
                    ],
                ],
            ],
        ];

        $model = $this->dao->createModelFromJsonDefinition($json);
        $this->fixtures->trackExisting('qa_categories', ['id_model' => $model->id]);
        $this->fixtures->trackExisting('qa_models', ['id' => $model->id]);

        $this->assertInstanceOf(ModelStruct::class, $model);
        $this->assertGreaterThan(0, $model->id);

        $conn = $this->realSqlDb()->getConnection();

        // two top-level categories (id_parent IS NULL); findByIdModelAndIdParent uses `= :id_parent`
        // which never matches NULL, so query them directly.
        $top = $conn->query(
            "SELECT id, label FROM qa_categories WHERE id_model = {$model->id} AND id_parent IS NULL ORDER BY label"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
        $this->assertCount(2, $top);
        $this->assertContains('Accuracy', $top);
        $this->assertContains('Fluency', $top);

        $accuracyId = (int)array_search('Accuracy', $top, true);

        // the Accuracy category has one subcategory (recursion wrote a non-null parent_id);
        // a non-null parent makes findByIdModelAndIdParent usable here.
        $subs = (new CategoryDao($this->realSqlDb()))->findByIdModelAndIdParent($model->id, $accuracyId);
        $this->assertCount(1, $subs);
        $this->assertSame('Mistranslation', $subs[0]->label);

        // total rows = 2 top + 1 sub
        $count = (int)$conn
            ->query("SELECT COUNT(*) FROM qa_categories WHERE id_model = {$model->id}")
            ->fetchColumn();
        $this->assertSame(3, $count);
    }
}
