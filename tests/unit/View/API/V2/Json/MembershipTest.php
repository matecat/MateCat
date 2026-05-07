<?php

namespace Tests\unit\View\API\V2\Json;

use Model\Teams\MembershipStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use View\API\V2\Json\Membership;

#[CoversClass(Membership::class)]
class MembershipTest extends TestCase
{
    private function makeMembership(int $id = 1, int $idTeam = 10, int $uid = 100): MembershipStruct
    {
        $struct           = new MembershipStruct();
        $struct->id       = $id;
        $struct->id_team  = $idTeam;
        $struct->uid      = $uid;
        $struct->is_admin = false;

        $user            = new UserStruct();
        $user->uid       = $uid;
        $user->email     = 'test@example.com';
        $user->first_name = 'Test';
        $user->last_name  = 'User';

        $struct->setUser($user);
        $struct->setUserMetadata([]);

        return $struct;
    }

    public function testConstructorAcceptsArray(): void
    {
        $view = new Membership([$this->makeMembership()]);
        $this->assertInstanceOf(Membership::class, $view);
    }

    public function testRenderItemReturnsExpectedKeys(): void
    {
        $membership = $this->makeMembership(5, 20);
        $view       = new Membership([$membership]);
        $result     = $view->renderItem($membership);

        $this->assertSame(5, $result['id']);
        $this->assertSame(20, $result['id_team']);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('projects', $result);
        $this->assertSame(0, $result['projects']);
    }

    public function testRenderItemIncludesUserMetadataWhenPresent(): void
    {
        $membership = $this->makeMembership();

        $metadata        = new \Model\Users\MetadataStruct();
        $metadata->key   = 'gplus_picture';
        $metadata->value = 'http://example.com/pic.jpg';
        $membership->setUserMetadata([$metadata]);

        $view   = new Membership([$membership]);
        $result = $view->renderItem($membership);

        $this->assertArrayHasKey('user_metadata', $result);
    }

    public function testRenderItemOmitsUserMetadataWhenEmpty(): void
    {
        $membership = $this->makeMembership();
        $view       = new Membership([$membership]);
        $result     = $view->renderItem($membership);

        $this->assertArrayNotHasKey('user_metadata', $result);
    }

    public function testRenderReturnsList(): void
    {
        $m1 = $this->makeMembership(1, 10, 100);
        $m2 = $this->makeMembership(2, 10, 200);

        $view   = new Membership([$m1, $m2]);
        $result = $view->render();

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame(2, $result[1]['id']);
    }

    public function testRenderPublicReturnsList(): void
    {
        $m1 = $this->makeMembership(1, 10, 100);

        $view   = new Membership([$m1]);
        $result = $view->renderPublic();

        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]);
    }

    public function testRenderItemPublicReturnsArray(): void
    {
        $membership = $this->makeMembership();
        $view       = new Membership([$membership]);
        $result     = $view->renderItemPublic($membership);

        $this->assertIsArray($result);
    }
}
