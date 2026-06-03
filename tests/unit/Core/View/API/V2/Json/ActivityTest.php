<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\ActivityLog\ActivityLogStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\V2\Json\Activity;

#[CoversClass(Activity::class)]
class ActivityTest extends AbstractTest
{
    private function makeStruct(int $id = 1, string $email = 'user@example.com'): ActivityLogStruct
    {
        $struct             = new ActivityLogStruct();
        $struct->ID         = $id;
        $struct->id_project = 10;
        $struct->id_job     = 20;
        $struct->action     = 1;
        $struct->email      = $email;
        $struct->first_name = 'Test';
        $struct->last_name  = 'User';
        $struct->ip         = '127.0.0.1';
        $struct->uid        = 100;
        $struct->event_date = '2024-01-01 00:00:00';

        return $struct;
    }

    public function testRenderEmptyReturnsEmptyArray(): void
    {
        $view   = new Activity([]);
        $result = $view->render();

        $this->assertSame([], $result);
    }

    public function testRenderSkipsNonActivityLogStructElements(): void
    {
        $view   = new Activity(['not-a-struct']);
        $result = $view->render();

        $this->assertSame([], $result);
    }

    public function testRenderReturnsExpectedKeys(): void
    {
        $struct = $this->makeStruct(5, 'a@b.com');
        $view   = new Activity([$struct]);
        $result = $view->render();

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('action', $result[0]);
        $this->assertArrayHasKey('email', $result[0]);
        $this->assertArrayHasKey('event_date', $result[0]);
        $this->assertArrayHasKey('first_name', $result[0]);
        $this->assertArrayHasKey('id_job', $result[0]);
        $this->assertArrayHasKey('id_project', $result[0]);
        $this->assertArrayHasKey('ip', $result[0]);
        $this->assertArrayHasKey('last_name', $result[0]);
        $this->assertArrayHasKey('uid', $result[0]);
    }

    public function testRenderCastsIdToInt(): void
    {
        $struct = $this->makeStruct(7);
        $view   = new Activity([$struct]);
        $result = $view->render();

        $this->assertSame(7, $result[0]['id']);
        $this->assertSame(20, $result[0]['id_job']);
        $this->assertSame(10, $result[0]['id_project']);
        $this->assertSame(100, $result[0]['uid']);
    }

    public function testRenderSetsAnonymousWhenEmailEmpty(): void
    {
        $struct        = $this->makeStruct(1, '');
        $view          = new Activity([$struct]);
        $result        = $view->render();

        $this->assertSame('Anonymous', $result[0]['first_name']);
        $this->assertSame('User', $result[0]['last_name']);
        $this->assertSame('Unknown', $result[0]['email']);
    }

    public function testRenderMultipleStructs(): void
    {
        $s1   = $this->makeStruct(1);
        $s2   = $this->makeStruct(2);
        $view = new Activity([$s1, $s2]);

        $this->assertCount(2, $view->render());
    }
}
