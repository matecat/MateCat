<?php


namespace Matecat\Core\Structs;

use Matecat\TestHelpers\AbstractTest;
use Model\Teams\MembershipDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;

class UserStructNullHandlingTest extends AbstractTest
{
    #[Test]
    public function shortName_returns_empty_string_when_names_are_null(): void
    {
        $struct = new UserStruct();
        $struct->first_name = null;
        $struct->last_name = null;

        $this->assertSame('', $struct->shortName());
    }

    #[Test]
    public function shortName_returns_initials_when_names_are_set(): void
    {
        $struct = new UserStruct();
        $struct->first_name = 'John';
        $struct->last_name = 'Doe';

        $this->assertSame('JD', $struct->shortName());
    }

    #[Test]
    public function belongsToTeam_returns_false_when_getUserTeams_returns_null(): void
    {
        $struct = $this->createStub(UserStruct::class);
        $struct->method('getUserTeams')->willReturn(null);

        $membershipDao = $this->createStub(MembershipDao::class);
        $this->assertFalse($struct->belongsToTeam(123, $membershipDao));
    }
}
