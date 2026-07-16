<?php

namespace Matecat\Core\DAO\TestQAModelTemplateDAO;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\LQA\QAModelTemplate\QAModelTemplateDao;
use Model\LQA\QAModelTemplate\QAModelTemplatePassfailStruct;
use Model\LQA\QAModelTemplate\QAModelTemplatePassfailThresholdStruct;
use Model\LQA\QAModelTemplate\QAModelTemplateStruct;
use PDOException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class QAModelTemplateDaoTest extends AbstractTest
{
    private QAModelTemplateDao $dao;
    private int $uid = 999999;
    /** @var array<int> */
    private array $createdIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new QAModelTemplateDao(obtainTestDatabase());
        $this->createdIds = [];
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdIds as $id) {
            try {
                $this->dao->remove($id, $this->uid);
            } catch (Exception) {
            }
        }
        $this->cleanupTestData();
        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec("DELETE FROM qa_model_templates WHERE uid = {$this->uid}");
    }

    private function makeValidJson(string $label = 'Test Template'): string
    {
        return json_encode([
            'model' => [
                'version' => 1,
                'label' => $label . ' ' . uniqid(),
                'categories' => [
                    [
                        'label' => 'Style',
                        'code' => 'STY',
                        'sort' => 1,
                        'severities' => [
                            ['label' => 'Neutral', 'code' => 'NEU', 'penalty' => 0, 'sort' => 1],
                            ['label' => 'Minor', 'code' => 'MIN', 'penalty' => 1, 'sort' => 2],
                        ]
                    ]
                ],
                'passfail' => [
                    'type' => 'points',
                    'thresholds' => [
                        ['label' => 'R1', 'value' => 5],
                        ['label' => 'R2', 'value' => 10],
                    ]
                ]
            ]
        ]);
    }

    private function create(string $label = 'Test Template'): QAModelTemplateStruct
    {
        $struct = $this->dao->createFromJSON($this->makeValidJson($label), $this->uid);
        $this->createdIds[] = $struct->id;

        return $struct;
    }

    #[Test]
    public function saveThrowsWhenPassfailIsNull(): void
    {
        $struct = new QAModelTemplateStruct();
        $struct->uid = 1;
        $struct->version = 1;
        $struct->label = 'Test';
        $struct->passfail = null;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('passfail');

        $this->dao->save($struct);
    }

    #[Test]
    public function updateThrowsWhenPassfailIsNull(): void
    {
        $struct = new QAModelTemplateStruct();
        $struct->id = 999;
        $struct->uid = 1;
        $struct->version = 1;
        $struct->label = 'Test';
        $struct->passfail = null;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('passfail');

        $this->dao->update($struct);
    }

    #[Test]
    public function getDefaultTemplateReturnsValidStructure(): void
    {
        $result = $this->dao->getDefaultTemplate(42);

        $this->assertIsArray($result);
        $this->assertSame(0, $result['id']);
        $this->assertSame(42, $result['uid']);
        $this->assertSame('Matecat original settings', $result['label']);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('passfail', $result);
        $this->assertArrayHasKey('createdAt', $result);
        $this->assertArrayHasKey('modifiedAt', $result);
        $this->assertNull($result['deletedAt']);
    }

    #[Test]
    public function getDefaultTemplateReturnsIsoFormattedDates(): void
    {
        $result = $this->dao->getDefaultTemplate(1);

        $this->assertNotNull($result['createdAt']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $result['createdAt']
        );
    }

    #[Test]
    public function getDefaultTemplateReturnsCategoriesWithSeverities(): void
    {
        $result = $this->dao->getDefaultTemplate(1);

        $this->assertNotEmpty($result['categories']);
        $firstCategory = $result['categories'][0];
        $this->assertArrayHasKey('id', $firstCategory);
        $this->assertArrayHasKey('label', $firstCategory);
        $this->assertArrayHasKey('severities', $firstCategory);
        $this->assertNotEmpty($firstCategory['severities']);
    }

    #[Test]
    public function getDefaultTemplateReturnsPassfailWithThresholds(): void
    {
        $result = $this->dao->getDefaultTemplate(1);

        $passfail = $result['passfail'];
        $this->assertSame(0, $passfail['id']);
        $this->assertCount(2, $passfail['thresholds']);
        $this->assertArrayHasKey('label', $passfail['thresholds'][0]);
        $this->assertArrayHasKey('value', $passfail['thresholds'][0]);
    }

    #[Test]
    public function getQaModelTemplateByIdAndUidThrowsWhenIdMissing(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('id and uid parameters must be provided');

        $this->dao->getQaModelTemplateByIdAndUid(['uid' => 1]);
    }

    #[Test]
    public function getQaModelTemplateByIdAndUidThrowsWhenUidMissing(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('id and uid parameters must be provided');

        $this->dao->getQaModelTemplateByIdAndUid(['id' => 1]);
    }

    #[Test]
    public function getReturnsNullForMissing(): void
    {
        $result = $this->dao->get(['id' => 999999999, 'uid' => 999999]);
        $this->assertNull($result);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function createFromJsonPersistsAndReturns(): void
    {
        $struct = $this->create('CRUD Create');

        $this->assertGreaterThan(0, $struct->id);
        $this->assertSame($this->uid, $struct->uid);
        $this->assertStringContainsString('CRUD Create', $struct->label);
        $this->assertNotEmpty($struct->categories);
        $this->assertNotNull($struct->passfail);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getReturnsCreatedTemplate(): void
    {
        $created = $this->create('Get Test');

        $fetched = $this->dao->get(['id' => $created->id, 'uid' => $this->uid]);

        $this->assertNotNull($fetched);
        $this->assertSame($created->id, $fetched->id);
        $this->assertStringContainsString('Get Test', $fetched->label);
        $this->assertNotEmpty($fetched->categories);
        $this->assertNotNull($fetched->passfail);
        $this->assertNotEmpty($fetched->passfail->thresholds);
        $this->assertNotEmpty($fetched->categories[0]->severities);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getByIdAndUidReturnsTemplate(): void
    {
        $created = $this->create('ByIdAndUid Test');

        $fetched = $this->dao->getQaModelTemplateByIdAndUid(['id' => $created->id, 'uid' => $this->uid]);

        $this->assertNotNull($fetched);
        $this->assertSame($created->id, $fetched->id);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getByIdAndUidReturnsNullForWrongUid(): void
    {
        $created = $this->create('Wrong Uid');

        $fetched = $this->dao->getQaModelTemplateByIdAndUid(['id' => $created->id, 'uid' => 888888]);

        $this->assertNull($fetched);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function editFromJsonUpdatesTemplate(): void
    {
        $created = $this->create('Before Edit');

        $editJson = $this->makeValidJson('After Edit');
        $updated = $this->dao->editFromJSON($created, $editJson);

        $this->assertSame($created->id, $updated->id);
        $this->assertStringContainsString('After Edit', $updated->label);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function removeSoftDeletesTemplate(): void
    {
        $created = $this->create('Delete Test');

        $count = $this->dao->remove($created->id, $this->uid);

        $this->assertSame(1, $count);
        $this->assertNull($this->dao->get(['id' => $created->id, 'uid' => $this->uid]));
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function removeReturnsZeroForNonExistent(): void
    {
        $count = $this->dao->remove(999999999, $this->uid);
        $this->assertSame(0, $count);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getAllPaginatedReturnsStructure(): void
    {
        $this->create('Paginated Test');

        $result = $this->dao->getAllPaginated($this->uid, '/api/v3/qa_model_template?page=', 1, 10);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertNotEmpty($result['items']);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function saveDirectlyPersists(): void
    {
        $struct = new QAModelTemplateStruct();
        $struct->uid = $this->uid;
        $struct->version = 1;
        $struct->label = 'Direct Save ' . uniqid();

        $passfail = new QAModelTemplatePassfailStruct();
        $passfail->passfail_type = 'points';
        $passfail->thresholds = [];

        $threshold = new QAModelTemplatePassfailThresholdStruct();
        $threshold->passfail_label = 'R1';
        $threshold->passfail_value = 5;
        $passfail->thresholds[] = $threshold;

        $struct->passfail = $passfail;
        $struct->categories = [];

        $saved = $this->dao->save($struct);
        $this->createdIds[] = $saved->id;

        $this->assertGreaterThan(0, $saved->id);
        $this->assertStringContainsString('Direct Save', $saved->label);
    }

    /**
     * save() opens a transaction, inserts the parent template, then the child rows. A NULL
     * penalty (NOT NULL column) makes the severity INSERT fail mid-transaction, exercising the
     * catch/rollBack/throw arm. The rollback must leave qa_model_templates at its prior count.
     */
    #[Test]
    #[Group('PersistenceNeeded')]
    public function saveRollsBackWhenAChildInsertFails(): void
    {
        $struct = (new QAModelTemplateStruct())->hydrateFromJSON($this->makeValidJson('Rollback Save'));
        $struct->uid = $this->uid;
        // NULL penalty violates the NOT NULL qa_model_template_severities.penalty column.
        $struct->categories[0]->severities[0]->penalty = null;

        $conn = obtainTestDatabase()->getConnection();
        $before = (int)$conn->query("SELECT COUNT(*) FROM qa_model_templates WHERE uid = {$this->uid}")->fetchColumn();

        try {
            $this->dao->save($struct);
            $this->fail('save() should have thrown on the NULL penalty child insert');
        } catch (PDOException) {
            // expected
        }

        $after = (int)$conn->query("SELECT COUNT(*) FROM qa_model_templates WHERE uid = {$this->uid}")->fetchColumn();
        $this->assertSame($before, $after, 'transaction must roll back the parent template insert');
    }

    /**
     * update() likewise wraps its writes in a transaction; a NULL penalty trips the
     * catch/rollBack/throw arm. Runs against a real, previously-saved template.
     */
    #[Test]
    #[Group('PersistenceNeeded')]
    public function updateRollsBackWhenAChildInsertFails(): void
    {
        $struct = $this->create('Rollback Update');
        $struct->categories[0]->severities[0]->penalty = null;

        $this->expectException(PDOException::class);
        $this->dao->update($struct);
    }

    /**
     * readDefaultQaModelJson() throws when the config file cannot be read. Point ROOT at a
     * missing path and swallow the file_get_contents warning so it returns false and the
     * explicit guard (not the warning) is what raises.
     */
    #[Test]
    public function getDefaultTemplateThrowsWhenConfigFileMissing(): void
    {
        $originalRoot = AppConfig::$ROOT;
        AppConfig::$ROOT = '/nonexistent_rsq_' . uniqid();
        set_error_handler(static fn(): bool => true);

        try {
            $this->dao->getDefaultTemplate(1);
            $this->fail('expected an exception when the QA model config file is missing');
        } catch (Exception $e) {
            $this->assertStringContainsString('Cannot read QA model configuration file', $e->getMessage());
        } finally {
            restore_error_handler();
            AppConfig::$ROOT = $originalRoot;
        }
    }
}
