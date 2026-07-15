<?php

namespace Matecat\Core\DAO\TestProjectTemplateDAO;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\Projects\ProjectTemplateDao;
use Model\Projects\ProjectTemplateStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;

class ProjectTemplateDaoTest extends AbstractTest
{
    private ProjectTemplateDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dao = new ProjectTemplateDao(obtainTestDatabase());
    }

    #[Test]
    public function createFromJSONThrowsWhenUserUidIsNull(): void
    {
        $user = new UserStruct();
        $user->uid = null;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('uid');

        $this->dao->createFromJSON((object)['name' => 'test'], $user);
    }

    #[Test]
    public function editFromJSONThrowsWhenUserUidIsNull(): void
    {
        $user = new UserStruct();
        $user->uid = null;

        $struct = new ProjectTemplateStruct();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('uid');

        $this->dao->editFromJSON($struct, (object)['name' => 'test'], 1, $user);
    }

    #[Test]
    public function updateThrowsWhenIdIsNull(): void
    {
        $struct = new ProjectTemplateStruct();
        $struct->id = null;
        $struct->uid = 1;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('id');

        $this->dao->update($struct);
    }
}
