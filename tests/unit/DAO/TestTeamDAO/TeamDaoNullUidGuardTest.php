<?php

use Model\Teams\TeamDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class TeamDaoNullUidGuardTest extends AbstractTest
{
    #[Test]
    public function getPersonalByUser_throws_DomainException_when_uid_is_null(): void
    {
        $user = new UserStruct();
        $user->uid = null;

        $this->expectException(DomainException::class);

        (new TeamDao())->getPersonalByUser($user);
    }
}
