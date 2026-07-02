<?php

namespace Matecat\Core\Model\Comments;

use Matecat\TestHelpers\AbstractTest;
use Model\Comments\CommentStruct;
use PHPUnit\Framework\Attributes\Test;

class CommentStructTest extends AbstractTest
{
    #[Test]
    public function getStructReturnsNewInstance(): void
    {
        $struct = CommentStruct::getStruct();
        $this->assertInstanceOf(CommentStruct::class, $struct);
    }

    #[Test]
    public function getThreadIdReturnsMd5(): void
    {
        $struct = new CommentStruct();
        $struct->id_job = 1;
        $struct->id_segment = 10;
        $struct->resolve_date = '2026-01-01 00:00:00';

        $expected = md5('1-10-2026-01-01 00:00:00');
        $this->assertSame($expected, $struct->getThreadId());
    }

    #[Test]
    public function jsonSerializeReturnsExpectedKeys(): void
    {
        $struct = new CommentStruct();
        $struct->id = 1;
        $struct->uid = 5;
        $struct->id_job = 10;
        $struct->id_segment = 100;
        $struct->is_anonymous = 0;
        $struct->full_name = 'Test User';
        $struct->source_page = 1;
        $struct->thread_id = 'abc123';
        $struct->message = 'Hello';
        $struct->message_type = 1;
        $struct->create_date = '2026-01-15 10:30:00';
        $struct->resolve_date = null;
        $struct->timestamp = 1737000000;

        $json = $struct->jsonSerialize();

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('uid', $json);
        $this->assertArrayHasKey('thread_id', $json);
        $this->assertArrayHasKey('timestamp', $json);
        $this->assertSame('Test User', $json['full_name']);
        $this->assertSame(1737000000, $json['timestamp']);
    }
}
