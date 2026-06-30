<?php

declare(strict_types=1);

namespace Matecat\Core\Model\ActivityLog;

use Matecat\TestHelpers\AbstractTest;
use Model\ActivityLog\ActivityLogDao;
use Model\ActivityLog\ActivityLogStruct;
use Model\DataAccess\Database;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('PersistenceNeeded')]
class ActivityLogDaoTest extends AbstractTest
{
    private ActivityLogDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new ActivityLogDao(obtainTestDatabase());
        obtainTestDatabase()->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        obtainTestDatabase()->getConnection()->rollBack();
        parent::tearDown();
    }

    #[Test]
    public function create_inserts_and_returns_id(): void
    {
        $struct = new ActivityLogStruct();
        $struct->id_project = 999999;
        $struct->id_job = 999999;
        $struct->uid = 1886428310;
        $struct->action = ActivityLogStruct::PROJECT_CREATED;
        $struct->ip = '127.0.0.1';
        $struct->event_date = date('Y-m-d H:i:s');

        $id = $this->dao->create($struct);

        $this->assertGreaterThan(0, $id);
    }

    #[Test]
    public function getAllForProject_returns_empty_for_nonexistent_project(): void
    {
        $result = $this->dao->getAllForProject(0);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function getLastActionInProject_returns_empty_for_nonexistent_project(): void
    {
        $result = $this->dao->getLastActionInProject(0);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function read_returns_empty_for_nonexistent_project(): void
    {
        $query = new ActivityLogStruct();
        $result = $this->dao->read($query, ['id_project' => 0]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
