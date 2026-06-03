<?php

namespace Matecat\Core\Search;

use Matecat\TestHelpers\AbstractTest;
use Model\Search\ReplaceEventCurrentVersionStruct;
use Model\Search\ReplaceEventStruct;
use Model\Search\SearchQueryParamsStruct;
use PHPUnit\Framework\Attributes\Test;

class SearchStructsTest extends AbstractTest
{
    #[Test]
    public function replaceEventStructDefaults(): void
    {
        $s = new ReplaceEventStruct();
        $this->assertNull($s->id);
        $this->assertSame(0, $s->segment_version);
        $this->assertNull($s->translation_before_replacement);
        $this->assertNull($s->source);
        $this->assertSame('', $s->created_at);
    }

    #[Test]
    public function replaceEventStructSetProperties(): void
    {
        $s = new ReplaceEventStruct();
        $s->id_job = 42;
        $s->replace_version = '3';
        $s->id_segment = 100;
        $s->target = 'hello';
        $s->status = 'TRANSLATED';
        $s->replacement = 'world';
        $s->translation_after_replacement = 'world';
        $s->job_password = 'abc123';

        $this->assertSame(42, $s->id_job);
        $this->assertSame('3', $s->replace_version);
        $this->assertSame(100, $s->id_segment);
        $this->assertSame('hello', $s->target);
        $this->assertSame('TRANSLATED', $s->status);
        $this->assertSame('world', $s->replacement);
        $this->assertSame('abc123', $s->job_password);
    }

    #[Test]
    public function replaceEventCurrentVersionStructDefaults(): void
    {
        $s = new ReplaceEventCurrentVersionStruct();
        $this->assertNull($s->id);
    }

    #[Test]
    public function replaceEventCurrentVersionStructSetProperties(): void
    {
        $s = new ReplaceEventCurrentVersionStruct();
        $s->id_job = 10;
        $s->version = 5;

        $this->assertSame(10, $s->id_job);
        $this->assertSame(5, $s->version);
    }

    #[Test]
    public function searchQueryParamsStructDefaults(): void
    {
        $s = new SearchQueryParamsStruct();
        $this->assertNull($s->key);
        $this->assertNull($s->target);
        $this->assertNull($s->source);
        $this->assertNull($s->replacement);
        $this->assertNull($s->status);
        $this->assertNull($s->matchCase);
        $this->assertNull($s->exactMatch);
    }

    #[Test]
    public function searchQueryParamsStructSetProperties(): void
    {
        $s = new SearchQueryParamsStruct();
        $s->job = 1;
        $s->password = 'pass';
        $s->target = 'target';
        $s->source = 'source';
        $s->replacement = 'repl';
        $s->status = 'TRANSLATED';
        $s->isMatchCaseRequested = true;
        $s->isExactMatchRequested = false;

        $this->assertSame(1, $s->job);
        $this->assertSame('pass', $s->password);
        $this->assertSame('target', $s->target);
        $this->assertTrue($s->isMatchCaseRequested);
        $this->assertFalse($s->isExactMatchRequested);
    }
}
