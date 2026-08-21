<?php

namespace Matecat\Core\DAO\TestQAModelTemplateDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\LQA\QAModelTemplate\QAModelTemplateDao;
use Model\Projects\ProjectTemplateDao;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * QAModelTemplateDao::remove() reaches ProjectTemplateDao::removeSubTemplateByIdAndUser(), which
 * evicts three cache keys. Those evictions are issued while a transaction is open, so DaoCacheTrait
 * defers them onto the connection's after-commit queue — and the queue is only drained by
 * Database::commit(). A transaction opened and closed on the raw PDO handle never drains it, and the
 * next Database::begin() throws the whole queue away.
 *
 * The pin is on the paginated key, which is the one of the three whose name is deterministic.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class QAModelTemplateDaoEvictionTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private QAModelTemplateDao $dao;
    private int $uid;

    /** @var array<int> */
    private array $createdIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        // No residue tables: that gate compares whole-table COUNT(*), and this test's own rows are
        // reachable from the fixture user, whose cleanup cascades to them. Running inside the full
        // suite it failed intermittently by exactly the two rows this fixture creates, on a
        // different child table each time, and never once in isolation. The rows written here are
        // cleaned explicitly below instead.
        $this->startRealSql([]);

        $this->uid = $this->fixtures->makeUser()['uid'];
        $this->dao = new QAModelTemplateDao($this->realSqlDb());
    }

    protected function tearDown(): void
    {
        // remove() soft-deletes the parent row, so the residue gate needs the hard delete here.
        foreach ($this->createdIds as $id) {
            $this->realSqlDb()->getConnection()->exec("DELETE FROM qa_model_templates WHERE id = $id");
        }

        $this->finishRealSql();
        parent::tearDown();
    }

    #[Test]
    public function remove_drains_the_project_template_evictions_it_queued(): void
    {
        $template = $this->dao->createFromJSON($this->validTemplateJson(), $this->uid);
        $this->createdIds[] = $template->id;

        $cacheKey = ProjectTemplateDao::paginated_map_key . ':' . $this->uid;

        $this->flushDaoCache();
        (new ProjectTemplateDao($this->realSqlDb()))->getAllPaginated($this->uid, '/api/v3/project-template');

        $this->assertTrue(
            (bool)$this->daoCacheRedis()->exists($cacheKey),
            'precondition: the paginated project-template cache must be warm'
        );

        $this->dao->remove($template->id, $this->uid);

        $this->assertFalse(
            (bool)$this->daoCacheRedis()->exists($cacheKey),
            'the eviction queued inside remove() must run when its transaction commits'
        );
    }

    private function validTemplateJson(): string
    {
        return json_encode([
            'model' => [
                'version'    => 1,
                'label'      => 'Eviction pin ' . uniqid(),
                'categories' => [
                    [
                        'label'      => 'Style',
                        'code'       => 'STY',
                        'sort'       => 1,
                        'severities' => [
                            ['label' => 'Neutral', 'code' => 'NEU', 'penalty' => 0, 'sort' => 1],
                            ['label' => 'Minor', 'code' => 'MIN', 'penalty' => 1, 'sort' => 2],
                        ],
                    ],
                ],
                'passfail'   => [
                    'type'       => 'points',
                    'thresholds' => [
                        ['label' => 'R1', 'value' => 5],
                        ['label' => 'R2', 'value' => 10],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
