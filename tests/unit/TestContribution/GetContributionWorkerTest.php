<?php

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\AsyncTasks\Workers\GetContributionWorker;
use Utils\ActiveMQ\AMQHandler;

class GetContributionWorkerTest extends AbstractTest
{
    private GetContributionWorker $worker;

    public function setUp(): void
    {
        parent::setUp();
        $this->worker = new GetContributionWorker(
            self::getStubBuilder(AMQHandler::class)->getStub()
        );
    }

    #[Test]
    public function test_tokenizeSourceSearch_returns_patterns_for_simple_text(): void
    {
        $method = new ReflectionMethod($this->worker, 'tokenizeSourceSearch');

        $result = $method->invoke($this->worker, 'Hello world');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        foreach ($result as $pattern => $replacement) {
            $this->assertIsString($pattern);
            $this->assertIsString($replacement);
            $this->assertStringContainsString('#{', $replacement);
            $this->assertStringContainsString('}#', $replacement);
        }
    }

    #[Test]
    public function test_tokenizeSourceSearch_strips_punctuation(): void
    {
        $method = new ReflectionMethod($this->worker, 'tokenizeSourceSearch');

        $result = $method->invoke($this->worker, 'Hello, world. (test)');

        $values = array_values($result);
        $found_hello = false;
        $found_world = false;
        $found_test  = false;
        foreach ($values as $v) {
            if (str_contains($v, '#{Hello}#')) {
                $found_hello = true;
            }
            if (str_contains($v, '#{world}#')) {
                $found_world = true;
            }
            if (str_contains($v, '#{test}#')) {
                $found_test = true;
            }
        }
        $this->assertTrue($found_hello, 'Should find Hello token');
        $this->assertTrue($found_world, 'Should find world token');
        $this->assertTrue($found_test, 'Should find test token');
    }

    #[Test]
    public function test_tokenizeSourceSearch_sorts_patterns_by_length_desc(): void
    {
        $method = new ReflectionMethod($this->worker, 'tokenizeSourceSearch');

        $result = $method->invoke($this->worker, 'a beautiful day');

        $keys    = array_keys($result);
        $lengths = array_map('strlen', $keys);

        for ($i = 0; $i < count($lengths) - 1; $i++) {
            $this->assertGreaterThanOrEqual($lengths[$i + 1], $lengths[$i],
                'Patterns should be sorted by length descending');
        }
    }

    #[Test]
    public function test_tokenizeSourceSearch_handles_empty_string(): void
    {
        $method = new ReflectionMethod($this->worker, 'tokenizeSourceSearch');

        $result = $method->invoke($this->worker, '');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function test_tokenizeSourceSearch_handles_html_entities(): void
    {
        $method = new ReflectionMethod($this->worker, 'tokenizeSourceSearch');

        $result = $method->invoke($this->worker, '&lt;tag&gt; content');

        $this->assertIsArray($result);
        $values = array_values($result);
        $found_content = false;
        foreach ($values as $v) {
            if (str_contains($v, '#{content}#')) {
                $found_content = true;
            }
        }
        $this->assertTrue($found_content, 'Should find content token');
    }

    #[Test]
    public function test_formatConcordanceValues_applies_regex_highlighting(): void
    {
        $method = new ReflectionMethod($this->worker, '_formatConcordanceValues');

        $regularExpressions = [
            '|(\s{1})?Hello(\s{1})?|ui' => '$1#{Hello}#$2',
        ];

        $result = $method->invoke($this->worker, 'Hello world', 'Target text', $regularExpressions);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertStringContainsString('#{Hello}#', $result[0]);
        $this->assertEquals('Target text', $result[1]);
    }

    #[Test]
    public function test_formatConcordanceValues_strips_tags_from_source(): void
    {
        $method = new ReflectionMethod($this->worker, '_formatConcordanceValues');

        $result = $method->invoke($this->worker, '<b>Bold</b> text', 'target', []);

        $this->assertEquals('Bold text', $result[0]);
        $this->assertEquals('target', $result[1]);
    }

    #[Test]
    public function test_formatConcordanceValues_collapses_multiple_spaces(): void
    {
        $method = new ReflectionMethod($this->worker, '_formatConcordanceValues');

        $result = $method->invoke($this->worker, 'Too   many    spaces', 'target', []);

        $this->assertStringNotContainsString('  ', $result[0]);
    }

    #[Test]
    public function test_matchRewrite_sets_ICE_MT_for_high_score(): void
    {
        $method = new ReflectionMethod($this->worker, '_matchRewrite');

        $match = ['score' => 0.95, 'match' => '95%', 'segment' => 'test'];
        $result = $method->invoke($this->worker, $match);

        $this->assertEquals('ICE_MT', $result['match']);
    }

    #[Test]
    public function test_matchRewrite_does_not_modify_low_score(): void
    {
        $method = new ReflectionMethod($this->worker, '_matchRewrite');

        $match = ['score' => 0.5, 'match' => '50%', 'segment' => 'test'];
        $result = $method->invoke($this->worker, $match);

        $this->assertEquals('50%', $result['match']);
    }

    #[Test]
    public function test_matchRewrite_does_not_modify_empty_score(): void
    {
        $method = new ReflectionMethod($this->worker, '_matchRewrite');

        $match = ['match' => '80%', 'segment' => 'test'];
        $result = $method->invoke($this->worker, $match);

        $this->assertEquals('80%', $result['match']);
    }

    #[Test]
    public function test_matchRewrite_boundary_score_0_9_triggers(): void
    {
        $method = new ReflectionMethod($this->worker, '_matchRewrite');

        $match = ['score' => 0.9, 'match' => '90%', 'segment' => 'test'];
        $result = $method->invoke($this->worker, $match);

        $this->assertEquals('ICE_MT', $result['match']);
    }

    #[Test]
    public function test_matchRewrite_score_just_below_0_9_does_not_trigger(): void
    {
        $method = new ReflectionMethod($this->worker, '_matchRewrite');

        $match = ['score' => 0.89, 'match' => '89%', 'segment' => 'test'];
        $result = $method->invoke($this->worker, $match);

        $this->assertEquals('89%', $result['match']);
    }

    #[Test]
    public function test_sortByLenDesc_longer_string_comes_first(): void
    {
        $method = new ReflectionMethod($this->worker, '_sortByLenDesc');

        $result = $method->invoke($this->worker, 'beautiful', 'a');

        $this->assertEquals(-1, $result);
    }

    #[Test]
    public function test_sortByLenDesc_shorter_string_comes_last(): void
    {
        $method = new ReflectionMethod($this->worker, '_sortByLenDesc');

        $result = $method->invoke($this->worker, 'a', 'beautiful');

        $this->assertEquals(1, $result);
    }

    #[Test]
    public function test_sortByLenDesc_equal_length_returns_zero(): void
    {
        $method = new ReflectionMethod($this->worker, '_sortByLenDesc');

        $result = $method->invoke($this->worker, 'abc', 'def');

        $this->assertEquals(0, $result);
    }
}
