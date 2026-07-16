<?php

namespace Matecat\Core\DAO\TestMTQEPayableRateTemplateDAO;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\MTQE\PayableRate\MTQEPayableRateStruct;
use Model\MTQE\PayableRate\MTQEPayableRateTemplateDao;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

class MTQEPayableRateTemplateDaoTest extends AbstractTest
{
    private MTQEPayableRateTemplateDao $dao;
    private int $uid = 999998;
    /** @var array<int> */
    private array $createdIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new MTQEPayableRateTemplateDao(obtainTestDatabase());
        $this->createdIds = [];
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec("DELETE FROM mt_qe_payable_rate_templates WHERE uid = {$this->uid}");
    }

    private function insertTemplate(string $name = 'Test MTQE PayableRate Template'): int
    {
        $conn = obtainTestDatabase()->getConnection();
        $breakdowns = json_encode(['breakdowns' => ['ice' => 0, 'tm_100' => 0, 'top_quality_mt' => 9]]);
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare(
            "INSERT INTO mt_qe_payable_rate_templates (uid, name, breakdowns, created_at) VALUES (:uid, :name, :breakdowns, :now)"
        );
        $stmt->execute([
            'uid' => $this->uid,
            'name' => $name . ' ' . uniqid(),
            'breakdowns' => $breakdowns,
            'now' => $now,
        ]);

        $id = (int)$conn->lastInsertId();
        $this->createdIds[] = $id;

        return $id;
    }

    #[Test]
    public function getDefaultTemplateReturnsValidStruct(): void
    {
        $result = $this->dao->getDefaultTemplate(42);

        $this->assertInstanceOf(MTQEPayableRateStruct::class, $result);
        $this->assertSame(0, $result->id);
        $this->assertSame(42, $result->uid);
        $this->assertSame('Matecat default settings', $result->name);
        $this->assertNotNull($result->created_at);
        $this->assertNotNull($result->breakdowns);
    }

    #[Test]
    public function getByIdReturnsNullForNonExistent(): void
    {
        $result = $this->dao->getById(999999999);
        $this->assertNull($result);
    }

    #[Test]
    public function getByIdAndUserReturnsNullForNonExistent(): void
    {
        $result = $this->dao->getByIdAndUser(999999999, 999999);
        $this->assertNull($result);
    }

    #[Test]
    public function getByUidReturnsEmptyForNoTemplates(): void
    {
        $result = $this->dao->getByUid(999999888);
        $this->assertSame([], $result);
    }

    #[Test]
    public function removeReturnsZeroForNonExistent(): void
    {
        $count = $this->dao->remove(999999999, 999999);
        $this->assertSame(0, $count);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getByIdReturnsInsertedTemplate(): void
    {
        $id = $this->insertTemplate('GetById Test');

        $result = $this->dao->getById($id);

        $this->assertNotNull($result);
        $this->assertSame($id, $result->id);
        $this->assertStringContainsString('GetById Test', $result->name);
        $this->assertNotNull($result->breakdowns);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getByIdAndUserReturnsInsertedTemplate(): void
    {
        $id = $this->insertTemplate('ByIdAndUser Test');

        $result = $this->dao->getByIdAndUser($id, $this->uid);

        $this->assertNotNull($result);
        $this->assertSame($id, $result->id);
        $this->assertStringContainsString('ByIdAndUser Test', $result->name);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getByIdAndUserReturnsNullForWrongUid(): void
    {
        $id = $this->insertTemplate('Wrong Uid');

        $this->assertNull($this->dao->getByIdAndUser($id, 888888));
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getByUidReturnsInsertedTemplates(): void
    {
        $this->insertTemplate('ByUid Test 1');
        $this->insertTemplate('ByUid Test 2');

        $result = $this->dao->getByUid($this->uid);

        $this->assertNotEmpty($result);
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function removeSoftDeletesTemplate(): void
    {
        $id = $this->insertTemplate('Delete Test');

        $count = $this->dao->remove($id, $this->uid);

        $this->assertSame(1, $count);
        $this->assertNull($this->dao->getById($id));
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getAllPaginatedReturnsStructure(): void
    {
        $this->insertTemplate('Paginated Test');

        $result = $this->dao->getAllPaginated($this->uid, '/api/v3/mtqe-payable-rate-template?page=', 1, 10);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertNotEmpty($result['items']);
    }
}
