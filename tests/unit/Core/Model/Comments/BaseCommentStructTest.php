<?php

namespace Matecat\Core\Model\Comments;

use Matecat\TestHelpers\AbstractTest;
use Model\Comments\BaseCommentStruct;
use PHPUnit\Framework\Attributes\Test;

class BaseCommentStructTest extends AbstractTest
{
    #[Test]
    public function getThreadIdReturnsNullWhenNoResolveDate(): void
    {
        $struct = new BaseCommentStruct();
        $struct->id_job = 1;
        $struct->id_segment = 10;
        $struct->resolve_date = null;

        $this->assertNull($struct->getThreadId());
    }

    #[Test]
    public function getThreadIdReturnsMd5WhenResolved(): void
    {
        $struct = new BaseCommentStruct();
        $struct->id_job = 1;
        $struct->id_segment = 10;
        $struct->resolve_date = '2026-01-01 00:00:00';

        $expected = md5('1-10-2026-01-01 00:00:00');
        $this->assertSame($expected, $struct->getThreadId());
    }

    #[Test]
    public function getFullNameReturnsTranslatorWhenAnonymousSourcePage1(): void
    {
        $struct = new BaseCommentStruct();
        $struct->is_anonymous = 1;
        $struct->source_page = 1;
        $struct->full_name = 'John';

        $this->assertSame('Translator', $struct->getFullName());
        $this->assertSame('the translator', $struct->getFullName(true));
    }

    #[Test]
    public function getFullNameReturnsRevisorWhenAnonymousSourcePage2(): void
    {
        $struct = new BaseCommentStruct();
        $struct->is_anonymous = 1;
        $struct->source_page = 2;
        $struct->full_name = 'John';

        $this->assertSame('Revisor', $struct->getFullName());
        $this->assertSame('the revisor', $struct->getFullName(true));
    }

    #[Test]
    public function getFullNameReturns2ndPassRevisorWhenAnonymousSourcePage3(): void
    {
        $struct = new BaseCommentStruct();
        $struct->is_anonymous = 1;
        $struct->source_page = 3;
        $struct->full_name = 'John';

        $this->assertSame('2nd pass revisor', $struct->getFullName());
        $this->assertSame('the 2nd pass revisor', $struct->getFullName(true));
    }

    #[Test]
    public function getFullNameReturnsActualNameWhenNotAnonymous(): void
    {
        $struct = new BaseCommentStruct();
        $struct->is_anonymous = 0;
        $struct->source_page = 1;
        $struct->full_name = 'John Doe';

        $this->assertSame('John Doe', $struct->getFullName());
    }

    #[Test]
    public function jsonSerializeReturnsExpectedKeys(): void
    {
        $struct = new BaseCommentStruct();
        $struct->id = 1;
        $struct->id_job = 10;
        $struct->id_segment = 100;
        $struct->create_date = '2026-01-15 10:30:00';
        $struct->full_name = 'Test User';
        $struct->uid = 5;
        $struct->resolve_date = null;
        $struct->is_anonymous = 0;
        $struct->source_page = 1;
        $struct->message_type = 1;
        $struct->message = 'Hello';

        $json = $struct->jsonSerialize();

        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('id_job', $json);
        $this->assertArrayHasKey('id_segment', $json);
        $this->assertArrayHasKey('create_at', $json);
        $this->assertArrayHasKey('full_name', $json);
        $this->assertArrayHasKey('resolved_at', $json);
        $this->assertArrayHasKey('thread_id', $json);
        $this->assertArrayHasKey('timestamp', $json);
        $this->assertNull($json['resolved_at']);
        $this->assertNull($json['thread_id']);
    }

    #[Test]
    public function jsonSerializeWithResolveDate(): void
    {
        $struct = new BaseCommentStruct();
        $struct->id = 1;
        $struct->id_job = 10;
        $struct->id_segment = 100;
        $struct->create_date = '2026-01-15 10:30:00';
        $struct->full_name = 'Test User';
        $struct->uid = 5;
        $struct->resolve_date = '2026-01-16 12:00:00';
        $struct->is_anonymous = 0;
        $struct->source_page = 1;
        $struct->message_type = 2;
        $struct->message = '';

        $json = $struct->jsonSerialize();

        $this->assertNotNull($json['resolved_at']);
        $this->assertNotNull($json['thread_id']);
    }
}
