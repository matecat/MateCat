<?php

use Model\Teams\MembershipStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

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
