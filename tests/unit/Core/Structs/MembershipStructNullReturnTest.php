<?php


namespace Matecat\Core\Structs;

use Matecat\TestHelpers\AbstractTest;
use Model\Teams\MembershipStruct;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class MembershipStructNullReturnTest extends AbstractTest
{
    #[Test]
    public function getUser_throws_when_uid_is_null(): void
    {
        $struct = new MembershipStruct();
        $struct->uid = null;

        $this->expectException(RuntimeException::class);
        $struct->getUser();
    }
}
