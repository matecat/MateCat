<?php

namespace Matecat\Core\Controller\API\Commons;

use Controller\API\Commons\Exceptions\ConflictError;
use Controller\API\Commons\JobRestructureLock;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\FakeLockRedisClient;
use PHPUnit\Framework\Attributes\Test;
use Predis\Connection\ConnectionException;
use Predis\Connection\NodeConnectionInterface;
use RuntimeException;

class JobRestructureLockTest extends AbstractTest
{
    private FakeLockRedisClient $redis;
    private JobRestructureLock $lock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redis = new FakeLockRedisClient();
        $this->lock = new JobRestructureLock($this->redis);
    }

    #[Test]
    public function acquire_sets_the_job_key_only_if_absent_with_a_one_hour_ttl(): void
    {
        $this->lock->acquire(42);

        self::assertCount(1, $this->redis->setCalls);
        [$key, $token, $ex, $ttl, $nx] = $this->redis->setCalls[0];
        self::assertSame(['job_restructure:42', 'EX', 3600, 'NX'], [$key, $ex, $ttl, $nx]);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
        self::assertSame($token, $this->redis->keys['job_restructure:42']);
    }

    #[Test]
    public function acquire_answers_conflict_while_another_request_holds_the_job(): void
    {
        $this->lock->acquire(42);

        $this->expectException(ConflictError::class);
        $this->expectExceptionMessage('This job is being changed by another request, try again in a few minutes');
        $this->lock->acquire(42);
    }

    #[Test]
    public function the_lock_is_per_job(): void
    {
        $this->lock->acquire(42);
        $this->lock->acquire(43);

        self::assertCount(2, $this->redis->keys);
    }

    #[Test]
    public function release_deletes_the_job_key(): void
    {
        $this->lock->acquire(42);
        $this->lock->release(42);

        self::assertSame([], $this->redis->keys);
        $this->lock->acquire(42);
    }

    #[Test]
    public function run_returns_what_the_action_returns_and_releases_the_job(): void
    {
        $result = $this->lock->run(42, fn(): string => 'done');

        self::assertSame('done', $result);
        self::assertSame(['set job_restructure:42', 'del job_restructure:42'], $this->redis->log);
    }

    #[Test]
    public function run_releases_the_job_when_the_action_throws(): void
    {
        try {
            $this->lock->run(42, fn() => throw new RuntimeException('boom'));
            self::fail('the action failure must reach the caller');
        } catch (RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        self::assertSame([], $this->redis->keys);
    }

    /**
     * The action already committed: Redis failing on the release must not turn it into a failed request.
     */
    #[Test]
    public function run_returns_the_result_when_the_release_fails(): void
    {
        $this->redis->failOnDel = $this->connectionLost();

        self::assertSame('done', $this->lock->run(42, fn(): string => 'done'));
    }

    /**
     * The caller has to see why the action failed, not why the release did.
     */
    #[Test]
    public function run_keeps_the_action_error_when_the_release_fails(): void
    {
        $this->redis->failOnDel = $this->connectionLost();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $this->lock->run(42, fn() => throw new RuntimeException('boom'));
    }

    /**
     * The request ran past the TTL and another one took the job: releasing must leave that one's lock.
     */
    #[Test]
    public function release_after_the_job_was_taken_by_another_request_leaves_its_lock(): void
    {
        $this->lock->acquire(42);
        unset($this->redis->keys['job_restructure:42']);
        $other = new JobRestructureLock($this->redis);
        $other->acquire(42);

        $this->lock->release(42);

        self::assertArrayHasKey('job_restructure:42', $this->redis->keys);
        self::assertSame([], $this->redis->deleted);
    }

    #[Test]
    public function release_of_a_job_this_instance_does_not_hold_sends_nothing(): void
    {
        $other = new JobRestructureLock($this->redis);
        $other->acquire(42);

        $this->lock->release(42);

        self::assertSame(['set job_restructure:42'], $this->redis->log);
        self::assertArrayHasKey('job_restructure:42', $this->redis->keys);
    }

    #[Test]
    public function a_job_released_once_is_not_released_again(): void
    {
        $this->lock->acquire(42);
        $this->lock->release(42);
        $this->lock->release(42);

        self::assertSame(['set job_restructure:42', 'del job_restructure:42'], $this->redis->log);
    }

    private function connectionLost(): ConnectionException
    {
        return new ConnectionException($this->createStub(NodeConnectionInterface::class), 'connection lost');
    }

    /**
     * A refused request must neither run nor delete the lock it did not take.
     */
    #[Test]
    public function run_on_a_held_job_neither_runs_the_action_nor_releases_the_holder(): void
    {
        $this->lock->acquire(42);
        $ran = false;

        try {
            $this->lock->run(42, function () use (&$ran): void {
                $ran = true;
            });
            self::fail('a held job must be refused');
        } catch (ConflictError) {
        }

        self::assertFalse($ran);
        self::assertArrayHasKey('job_restructure:42', $this->redis->keys);
        self::assertSame([], $this->redis->deleted);
    }
}
