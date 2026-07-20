<?php

namespace Matecat\Core\Utils\LQA;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Utils\LQA\ChunkReviewJobLock;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class ChunkReviewJobLockTest extends AbstractTest
{
    /** @var string|array<string|int, string> */
    private string|array $originalServers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServers = AppConfig::$REDIS_SERVERS;
    }

    protected function tearDown(): void
    {
        AppConfig::$REDIS_SERVERS = $this->originalServers;
        parent::tearDown();
    }

    #[Test]
    public function run_executes_callback_and_returns_its_value(): void
    {
        $result = ChunkReviewJobLock::run(90001, fn() => 'callback-result');

        $this->assertSame('callback-result', $result);
    }

    #[Test]
    public function run_releases_the_lock_after_the_callback_completes(): void
    {
        $idJob = 90002;

        ChunkReviewJobLock::run($idJob, fn() => null);

        // once released, a second caller must acquire the same lock immediately (no wait needed).
        $start = microtime(true);
        ChunkReviewJobLock::run($idJob, fn() => null, 5);
        $this->assertLessThan(1.0, microtime(true) - $start);
    }

    #[Test]
    public function run_releases_the_lock_even_when_the_callback_throws(): void
    {
        $idJob = 90003;

        try {
            ChunkReviewJobLock::run($idJob, function () {
                throw new RuntimeException('boom');
            });
            $this->fail('Expected exception was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        // the lock must have been released in the finally block, not left dangling.
        $start = microtime(true);
        ChunkReviewJobLock::run($idJob, fn() => null, 5);
        $this->assertLessThan(1.0, microtime(true) - $start);
    }

    #[Test]
    public function run_waits_for_a_lock_held_by_another_holder_before_giving_up(): void
    {
        $idJob = 90004;
        $lockKey = 'qa_chunk_review:job:' . $idJob;

        // Simulate a concurrent holder (e.g. a split/merge in progress) that never releases
        // within the wait window — confirms run() actually waits rather than racing straight
        // through, while still honoring the best-effort contract once the wait times out.
        $holder = new RedisHandler();
        $holder->tryLock($lockKey, 5);

        $executed = false;
        $start = microtime(true);
        ChunkReviewJobLock::run($idJob, function () use (&$executed) {
            $executed = true;
        }, 1);
        $elapsed = microtime(true) - $start;

        $this->assertTrue($executed, 'best-effort contract: callback must still run once the wait times out');
        $this->assertGreaterThanOrEqual(1.0, $elapsed);

        $holder->unlock($lockKey);
    }

    #[Test]
    public function run_proceeds_without_a_lock_when_redis_is_unavailable(): void
    {
        // tcp://127.0.0.1:1 is a closed local port -> connection refused immediately (no hang).
        AppConfig::$REDIS_SERVERS = 'tcp://127.0.0.1:1';

        $executed = false;

        ChunkReviewJobLock::run(90005, function () use (&$executed) {
            $executed = true;
        });

        $this->assertTrue($executed, 'best-effort contract: callback must still run when Redis is unreachable');
    }
}
