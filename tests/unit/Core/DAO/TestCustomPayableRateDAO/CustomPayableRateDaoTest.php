<?php

namespace Matecat\Core\DAO\TestCustomPayableRateDAO;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\Analysis\PayableRates;
use Model\DataAccess\Database;
use Model\PayableRates\CustomPayableRateDao;
use Model\PayableRates\CustomPayableRateStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

class CustomPayableRateDaoTest extends AbstractTest
{
    private const array TEST_UIDS = [999997, 999998, 999999];

    private CustomPayableRateDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new CustomPayableRateDao();
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    private function cleanupTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $uids = implode(',', self::TEST_UIDS);
        $conn->exec("DELETE FROM payable_rate_templates WHERE uid IN ($uids)");
        $conn->exec("DELETE FROM job_custom_payable_rates WHERE id_job = 999999");
    }

    private function makeBreakdowns(): array
    {
        return ['default' => PayableRates::$DEFAULT_PAYABLE_RATES];
    }

    #[Test]
    public function saveThrowsWhenUidIsNull(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->uid = null;
        $struct->name = 'test';
        $struct->breakdowns = $this->makeBreakdowns();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('uid');

        $this->dao->save($struct);
    }

    #[Test]
    public function updateThrowsWhenIdIsNull(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->id = null;
        $struct->uid = 1;
        $struct->name = 'test';
        $struct->version = 1;
        $struct->breakdowns = $this->makeBreakdowns();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('id');

        $this->dao->update($struct);
    }

    #[Test]
    public function updateThrowsWhenUidIsNull(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->id = 1;
        $struct->uid = null;
        $struct->name = 'test';
        $struct->version = 1;
        $struct->breakdowns = $this->makeBreakdowns();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('uid');

        $this->dao->update($struct);
    }

    #[Test]
    public function getDefaultTemplateReturnsValidStructure(): void
    {
        $result = $this->dao->getDefaultTemplate(42);

        $this->assertIsArray($result);
        $this->assertSame(0, $result['id']);
        $this->assertSame(42, $result['uid']);
        $this->assertSame('Matecat original settings', $result['payable_rate_template_name']);
        $this->assertArrayHasKey('breakdowns', $result);
        $this->assertArrayHasKey('createdAt', $result);
        $this->assertArrayHasKey('modifiedAt', $result);
        $this->assertNotNull($result['createdAt']);
        $this->assertNotNull($result['modifiedAt']);
    }

    #[Test]
    public function createFromJsonThrowsOnInvalidJson(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot instantiate');

        $this->dao->createFromJSON('{"invalid": true}');
    }

    #[Test]
    public function getDefaultTemplateVersionIsOne(): void
    {
        $result = $this->dao->getDefaultTemplate(99);

        $this->assertSame(1, $result['version']);
    }

    #[Test]
    public function getDefaultTemplateContainsDefaultBreakdown(): void
    {
        $result = $this->dao->getDefaultTemplate(1);

        $this->assertArrayHasKey('default', $result['breakdowns']);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function fullCrudCycle(): void
    {
        $breakdowns = $this->makeBreakdowns();
        $json = json_encode([
            'payable_rate_template_name' => 'Test CRUD Template',
            'breakdowns' => $breakdowns
        ]);

        $struct = $this->dao->createFromJSON($json, 999999);

        $this->assertNotNull($struct->id);
        $this->assertSame(999999, $struct->uid);
        $this->assertSame('Test CRUD Template', $struct->name);
        $this->assertSame(1, $struct->version);
        $this->assertNotNull($struct->created_at);
        $this->assertNotNull($struct->modified_at);

        $found = $this->dao->findById($struct->id);
        $this->assertNotNull($found);
        $this->assertSame($struct->id, $found->id);
        $this->assertSame('Test CRUD Template', $found->name);

        $foundByUser = $this->dao->getByIdAndUser($struct->id, 999999);
        $this->assertNotNull($foundByUser);
        $this->assertSame($struct->id, $foundByUser->id);

        $notFound = $this->dao->getByIdAndUser($struct->id, 888888);
        $this->assertNull($notFound);

        $editJson = json_encode([
            'payable_rate_template_name' => 'Updated Template',
            'breakdowns' => $breakdowns
        ]);
        $updated = $this->dao->editFromJSON($foundByUser, $editJson);
        $this->assertSame('Updated Template', $updated->name);

        $count = $this->dao->remove($struct->id, 999999);
        $this->assertSame(1, $count);

        $afterDelete = $this->dao->findById($struct->id);
        $this->assertNull($afterDelete);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function saveAndUpdateDirectly(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->uid = 999998;
        $struct->name = 'Direct Save Test';
        $struct->breakdowns = $this->makeBreakdowns();

        $saved = $this->dao->save($struct);
        $this->assertNotNull($saved->id);
        $this->assertSame(1, $saved->version);

        $saved->name = 'Direct Update Test';
        $updated = $this->dao->update($saved);
        $this->assertSame('Direct Update Test', $updated->name);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getAllPaginatedReturnsStructure(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->uid = 999997;
        $struct->name = 'Paginated Test';
        $struct->breakdowns = $this->makeBreakdowns();
        $this->dao->save($struct);

        $result = $this->dao->getAllPaginated(999997, '/test?page=');
        $this->assertArrayHasKey('items', $result);
        $this->assertNotEmpty($result['items']);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function removeNonExistentReturnsZero(): void
    {
        $count = $this->dao->remove(999999999, 999999);
        $this->assertSame(0, $count);
    }

    #[Test]
    public function findByIdReturnsNullForMissing(): void
    {
        $result = $this->dao->findById(999999999);
        $this->assertNull($result);
    }

    #[Test]
    public function getByIdAndUserReturnsNullForMissing(): void
    {
        $result = $this->dao->getByIdAndUser(999999999, 1);
        $this->assertNull($result);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function assocModelToJobInsertsRow(): void
    {
        $this->dao->assocModelToJob(1, 999999, 1, 'Test Model');

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare("SELECT * FROM job_custom_payable_rates WHERE id_job = :id_job");
        $stmt->execute(['id_job' => 999999]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertSame(1, (int)$row['custom_payable_rate_model_id']);
        $this->assertSame('Test Model', $row['custom_payable_rate_model_name']);
    }
}
