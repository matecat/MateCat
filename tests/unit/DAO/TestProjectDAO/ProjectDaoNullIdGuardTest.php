<?php

use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

/**
 * @covers \Model\Projects\ProjectDao::changePassword
 * @covers \Model\Projects\ProjectDao::changeName
 */
class ProjectDaoNullIdGuardTest extends AbstractTest
{
    private ProjectDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new ProjectDao();
    }

    #[Test]
    public function changePassword_throws_DomainException_when_project_id_is_null(): void
    {
        $project = new ProjectStruct();
        $project->id = null;
        $project->password = 'oldpass';

        $this->expectException(DomainException::class);

        $this->dao->changePassword($project, 'newpass');
    }

    #[Test]
    public function changeName_throws_DomainException_when_project_id_is_null(): void
    {
        $project = new ProjectStruct();
        $project->id = null;
        $project->name = 'Old Name';

        $this->expectException(DomainException::class);

        $this->dao->changeName($project, 'New Name');
    }
}
