<?php

namespace Matecat\Core\TaskRunner\Commons;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Commons\ContextList;

class ContextListTest extends AbstractTest
{
    // ─── get() with empty array ──────────────────────────────────────────────────

    #[Test]
    public function get_with_empty_array_returns_empty_list(): void
    {
        $contextList = ContextList::get([]);

        $this->assertInstanceOf(ContextList::class, $contextList);
        $this->assertEmpty($contextList->list);
    }

    // ─── get() with queue data ───────────────────────────────────────────────────

    #[Test]
    public function get_with_single_queue_populates_list(): void
    {
        $queueInfo = [
            'fast' => [
                'queue_name'    => 'fast_queue',
                'max_executors' => 5,
            ],
        ];

        $contextList = ContextList::get($queueInfo);

        $this->assertCount(1, $contextList->list);
    }

    #[Test]
    public function get_with_multiple_queues_populates_all_entries(): void
    {
        $queueInfo = [
            'fast'   => [
                'queue_name'    => 'fast_queue',
                'max_executors' => 5,
            ],
            'medium' => [
                'queue_name'    => 'medium_queue',
                'max_executors' => 3,
            ],
            'slow'   => [
                'queue_name'    => 'slow_queue',
                'max_executors' => 1,
            ],
        ];

        $contextList = ContextList::get($queueInfo);

        $this->assertCount(3, $contextList->list);
    }

    // ─── Keys in $list match input keys ─────────────────────────────────────────

    #[Test]
    public function list_keys_match_input_keys(): void
    {
        $queueInfo = [
            'fast'   => [
                'queue_name'    => 'fast_queue',
                'max_executors' => 5,
            ],
            'medium' => [
                'queue_name'    => 'medium_queue',
                'max_executors' => 3,
            ],
        ];

        $contextList = ContextList::get($queueInfo);

        $this->assertArrayHasKey('fast', $contextList->list);
        $this->assertArrayHasKey('medium', $contextList->list);
    }

    // ─── Each list entry is a Context instance ───────────────────────────────────

    #[Test]
    public function each_list_entry_is_a_context_instance(): void
    {
        $queueInfo = [
            'fast'   => [
                'queue_name'    => 'fast_queue',
                'max_executors' => 5,
            ],
            'medium' => [
                'queue_name'    => 'medium_queue',
                'max_executors' => 3,
            ],
        ];

        $contextList = ContextList::get($queueInfo);

        foreach ($contextList->list as $entry) {
            $this->assertInstanceOf(Context::class, $entry);
        }
    }

    // ─── Context values are populated correctly ──────────────────────────────────

    #[Test]
    public function context_values_match_queue_info(): void
    {
        $queueInfo = [
            'fast' => [
                'queue_name'    => 'fast_queue',
                'max_executors' => 7,
            ],
        ];

        $contextList = ContextList::get($queueInfo);

        $context = $contextList->list['fast'];
        $this->assertSame('fast_queue', $context->queue_name);
        $this->assertSame(7, $context->max_executors);
    }

    // ─── get() with default argument ────────────────────────────────────────────

    #[Test]
    public function get_with_default_argument_returns_empty_list(): void
    {
        $contextList = ContextList::get();

        $this->assertInstanceOf(ContextList::class, $contextList);
        $this->assertEmpty($contextList->list);
    }
}
