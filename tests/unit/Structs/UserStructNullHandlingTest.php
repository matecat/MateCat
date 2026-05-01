<?php

use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

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
        $struct = $this->getMockBuilder(UserStruct::class)
            ->onlyMethods(['getUserTeams'])
            ->getMock();

        $struct->method('getUserTeams')->willReturn(null);

        $this->assertFalse($struct->belongsToTeam(123));
    }
}
