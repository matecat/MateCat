<?php


namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Model\Teams\MembershipDao;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Users\MetadataDao;
use Model\Users\MetadataStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use stdClass;

class UserStructDITest extends AbstractTest
{
    private function makeUser(): UserStruct
    {
        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.com';
        $user->first_name = 'John';
        $user->last_name = 'Doe';

        return $user;
    }

    #[Test]
    public function getPersonalTeamUsesInjectedDao(): void
    {
        $team = new TeamStruct();
        $team->id = 10;
        $team->name = 'Personal';

        $dao = $this->createStub(TeamDao::class);
        $dao->method('getPersonalByUser')->willReturn($team);

        $user = $this->makeUser();
        $result = $user->getPersonalTeam($dao);

        $this->assertSame(10, $result->id);
    }

    #[Test]
    public function getUserTeamsUsesInjectedDao(): void
    {
        $team1 = new TeamStruct();
        $team1->id = 1;
        $team2 = new TeamStruct();
        $team2->id = 2;

        $dao = $this->createStub(MembershipDao::class);
        $dao->method('findUserTeams')->willReturn([$team1, $team2]);

        $user = $this->makeUser();
        $result = $user->getUserTeams($dao);

        $this->assertCount(2, $result);
    }

    #[Test]
    public function getUserTeamsReturnsNull(): void
    {
        $dao = $this->createStub(MembershipDao::class);
        $dao->method('findUserTeams')->willReturn(null);

        $user = $this->makeUser();
        $this->assertNull($user->getUserTeams($dao));
    }

    #[Test]
    public function belongsToTeamReturnsTrueWhenMember(): void
    {
        $team = new TeamStruct();
        $team->id = 5;

        $dao = $this->createStub(MembershipDao::class);
        $dao->method('findUserTeams')->willReturn([$team]);

        $user = $this->makeUser();
        $this->assertTrue($user->belongsToTeam(5, $dao));
    }

    #[Test]
    public function belongsToTeamReturnsFalseWhenNotMember(): void
    {
        $team = new TeamStruct();
        $team->id = 5;

        $dao = $this->createStub(MembershipDao::class);
        $dao->method('findUserTeams')->willReturn([$team]);

        $user = $this->makeUser();
        $this->assertFalse($user->belongsToTeam(99, $dao));
    }

    #[Test]
    public function belongsToTeamReturnsFalseWhenNoTeams(): void
    {
        $dao = $this->createStub(MembershipDao::class);
        $dao->method('findUserTeams')->willReturn(null);

        $user = $this->makeUser();
        $this->assertFalse($user->belongsToTeam(1, $dao));
    }

    #[Test]
    public function getMetadataAsKeyValueUsesInjectedDao(): void
    {
        $meta1 = new MetadataStruct();
        $meta1->key = 'dictation';
        $meta1->value = '1';

        $meta2 = new MetadataStruct();
        $meta2->key = 'lexiqa';
        $meta2->value = '0';

        $dao = $this->createStub(MetadataDao::class);
        $dao->method('getAllByUid')->willReturn([$meta1, $meta2]);

        $user = $this->makeUser();
        $result = $user->getMetadataAsKeyValue($dao);

        $this->assertSame(1, $result['dictation']);
        $this->assertSame(0, $result['lexiqa']);
        $this->assertArrayHasKey('show_whitespace', $result);
        $this->assertArrayHasKey('ai_assistant', $result);
    }

    #[Test]
    public function getMetadataAsKeyValueThrowsWhenUidNull(): void
    {
        $this->expectException(RuntimeException::class);

        $dao = $this->createStub(MetadataDao::class);
        $user = new UserStruct();
        $user->getMetadataAsKeyValue($dao);
    }

    #[Test]
    public function getMetadataAsKeyValueFillsDefaults(): void
    {
        $dao = $this->createStub(MetadataDao::class);
        $dao->method('getAllByUid')->willReturn([]);

        $user = $this->makeUser();
        $result = $user->getMetadataAsKeyValue($dao);

        $this->assertSame(0, $result['dictation']);
        $this->assertSame(0, $result['show_whitespace']);
        $this->assertSame(1, $result['guess_tags']);
        $this->assertSame(1, $result['lexiqa']);
        $this->assertSame(0, $result['character_counter']);
        $this->assertSame(0, $result['ai_assistant']);
        $this->assertInstanceOf(stdClass::class, $result['cross_language_matches']);
    }
}
