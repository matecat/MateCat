<?php

namespace Matecat\Core\TaskRunner\Commons;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\TaskRunner\Commons\Context;

class ContextTest extends AbstractTest
{
    private array $queueElement = [
        'queue_name'    => 'test_queue',
        'max_executors' => 5,
    ];

    // ─── buildFromArray sets all properties ─────────────────────────────────────

    #[Test]
    public function buildFromArray_sets_queue_name(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertSame('test_queue', $context->queue_name);
    }

    #[Test]
    public function buildFromArray_sets_max_executors(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertSame(5, $context->max_executors);
    }

    #[Test]
    public function buildFromArray_sets_pid_list_len_to_zero(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertSame(0, $context->pid_list_len);
    }

    // ─── pid_set_name derived from queue_name ────────────────────────────────────

    #[Test]
    public function buildFromArray_derives_pid_set_name_from_queue_name(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertSame('test_queue_pid_set', $context->pid_set_name);
    }

    // ─── redis_key derived from queue_name ───────────────────────────────────────

    #[Test]
    public function buildFromArray_derives_redis_key_from_queue_name(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertSame('test_queue_redis_key', $context->redis_key);
    }

    // ─── loggerName derived from queue_name ──────────────────────────────────────

    #[Test]
    public function buildFromArray_derives_logger_name_from_queue_name(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertSame('test_queue.log', $context->loggerName);
    }

    // ─── buildFromArray returns Context instance ─────────────────────────────────

    #[Test]
    public function buildFromArray_returns_context_instance(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $this->assertInstanceOf(Context::class, $context);
    }

    // ─── __toString returns valid JSON ───────────────────────────────────────────

    #[Test]
    public function toString_returns_valid_json_string(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $json = (string) $context;

        $this->assertJson($json);
    }

    #[Test]
    public function toString_json_contains_queue_name(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $json   = (string) $context;
        $decoded = json_decode($json, true);

        $this->assertSame('test_queue', $decoded['queue_name']);
    }

    #[Test]
    public function toString_json_contains_max_executors(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $json    = (string) $context;
        $decoded = json_decode($json, true);

        $this->assertSame(5, $decoded['max_executors']);
    }

    #[Test]
    public function toString_json_contains_pid_set_name(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $json    = (string) $context;
        $decoded = json_decode($json, true);

        $this->assertSame('test_queue_pid_set', $decoded['pid_set_name']);
    }

    #[Test]
    public function toString_json_contains_redis_key(): void
    {
        $context = Context::buildFromArray($this->queueElement);

        $json    = (string) $context;
        $decoded = json_decode($json, true);

        $this->assertSame('test_queue_redis_key', $decoded['redis_key']);
    }

    // ─── Different queue names produce correct derived values ────────────────────

    #[Test]
    public function buildFromArray_with_different_queue_name_derives_correct_values(): void
    {
        $context = Context::buildFromArray([
            'queue_name'    => 'analysis_queue',
            'max_executors' => 10,
        ]);

        $this->assertSame('analysis_queue', $context->queue_name);
        $this->assertSame('analysis_queue_pid_set', $context->pid_set_name);
        $this->assertSame('analysis_queue_redis_key', $context->redis_key);
        $this->assertSame('analysis_queue.log', $context->loggerName);
        $this->assertSame(10, $context->max_executors);
    }
}
