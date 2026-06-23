<?php


namespace Matecat\Core\Structs;

use Matecat\TestHelpers\AbstractTest;
use Model\Teams\MembershipStruct;
use Model\Users\UserDao;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class MembershipStructNullReturnTest extends AbstractTest
{
    #[Test]
    public function getUser_throws_when_uid_is_null(): void
    {
        $struct = new MembershipStruct();
        $struct->uid = null;

        $mockDao = $this->createMock(UserDao::class);
        $mockDao->expects($this->never())->method('getByUid');

        $this->expectException(RuntimeException::class);
        $struct->getUser($mockDao);
    }
}
