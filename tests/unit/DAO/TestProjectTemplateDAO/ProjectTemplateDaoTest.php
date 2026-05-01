<?php

namespace unit\DAO\TestProjectTemplateDAO;

use Exception;
use Model\Projects\ProjectTemplateDao;
use Model\Projects\ProjectTemplateStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class ProjectTemplateDaoTest extends AbstractTest
{
    #[Test]
    public function createFromJSONThrowsWhenUserUidIsNull(): void
    {
        $user = new UserStruct();
        $user->uid = null;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('uid');

        ProjectTemplateDao::createFromJSON((object)['name' => 'test'], $user);
    }

    #[Test]
    public function editFromJSONThrowsWhenUserUidIsNull(): void
    {
        $user = new UserStruct();
        $user->uid = null;

        $struct = new ProjectTemplateStruct();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('uid');

        ProjectTemplateDao::editFromJSON($struct, (object)['name' => 'test'], 1, $user);
    }

    #[Test]
    public function updateThrowsWhenIdIsNull(): void
    {
        $struct = new ProjectTemplateStruct();
        $struct->id = null;
        $struct->uid = 1;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('id');

        ProjectTemplateDao::update($struct);
    }
}
