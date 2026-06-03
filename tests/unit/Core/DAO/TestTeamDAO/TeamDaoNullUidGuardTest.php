<?php


namespace Matecat\Core\DAO\TestTeamDAO;

use DomainException;
use Matecat\TestHelpers\AbstractTest;
use Model\Teams\TeamDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;

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
