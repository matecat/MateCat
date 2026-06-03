<?php


namespace Matecat\Core\Model\Teams;

use Matecat\TestHelpers\AbstractTest;
use Model\Teams\MembershipStruct;
use Model\Teams\TeamStruct;
use PHPUnit\Framework\Attributes\Test;

class TeamStructTest extends AbstractTest
{
    #[Test]
    public function getMembersReturnsEmptyArrayByDefault(): void
    {
        $struct = new TeamStruct();
        $this->assertSame([], $struct->getMembers());
    }

    #[Test]
    public function setMembersAndGetMembers(): void
    {
        $m1 = new MembershipStruct();
        $m1->uid = 1;
        $m2 = new MembershipStruct();
        $m2->uid = 2;

        $struct = new TeamStruct();
        $result = $struct->setMembers([$m1, $m2]);

        $this->assertSame($struct, $result);
        $this->assertCount(2, $struct->getMembers());
    }

    #[Test]
    public function hasUserReturnsTrueWhenMemberExists(): void
    {
        $m = new MembershipStruct();
        $m->uid = 42;

        $struct = new TeamStruct();
        $struct->setMembers([$m]);

        $this->assertTrue($struct->hasUser(42));
    }

    #[Test]
    public function hasUserReturnsFalseWhenNotMember(): void
    {
        $m = new MembershipStruct();
        $m->uid = 42;

        $struct = new TeamStruct();
        $struct->setMembers([$m]);

        $this->assertFalse($struct->hasUser(99));
    }

    #[Test]
    public function hasUserReturnsFalseWhenNoMembers(): void
    {
        $struct = new TeamStruct();
        $this->assertFalse($struct->hasUser(1));
    }
}
