<?php

namespace unit\DAO;

use Model\DataAccess\XFetchEnvelope;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use ReflectionClass;

/**
 * Tests for the XFetch probabilistic early expiration algorithm
 * implemented in DaoCacheTrait.
 */
class XFetchAlgorithmTest extends AbstractTest
{
    /**
     * Concrete class that uses DaoCacheTrait for testing.
     * We use a minimal stub to avoid DB dependencies.
     */
    private object $traitUser;
    private ReflectionClass $reflector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->traitUser = new class {
            use \Model\DataAccess\DaoCacheTrait;

            // Expose protected methods for testing
            public function publicShouldRecompute(float $storedAt, float $delta, int $ttl): bool
            {
                return $this->_shouldRecompute($storedAt, $delta, $ttl);
            }

            public function publicSetLastComputeDelta(float $delta): void
            {
                $this->_setLastComputeDelta($delta);
            }

            public function getLastComputeDelta(): float
            {
                return $this->lastComputeDelta;
            }

            public function getXfetchEnabled(): bool
            {
                return $this->xFetchEnabled;
            }
        };
        $this->reflector = new ReflectionClass($this->traitUser);
    }

    // --- XFetchEnvelope tests ---

    #[Test]
    public function test_xfetchEnvelope_constructorSetsProperties(): void
    {
        $data = [['id' => 1, 'name' => 'test']];
        $envelope = new XFetchEnvelope($data, 1711500000.0, 0.042);

        $this->assertSame($data, $envelope->value);
        $this->assertSame(1711500000.0, $envelope->storedAt);
        $this->assertSame(0.042, $envelope->delta);
    }

    #[Test]
    public function test_xfetchEnvelope_serializeRoundtrip(): void
    {
        $data = [['id' => 1]];
        $original = new XFetchEnvelope($data, 1711500000.0, 0.05);
        $restored = unserialize(serialize($original));

        $this->assertInstanceOf(XFetchEnvelope::class, $restored);
        $this->assertSame($data, $restored->value);
        $this->assertSame(1711500000.0, $restored->storedAt);
        $this->assertSame(0.05, $restored->delta);
    }

    #[Test]
    public function test_xfetchEnvelope_instanceofDetectsEnvelope(): void
    {
        $envelope = new XFetchEnvelope([], 0.0, 0.0);
        $this->assertInstanceOf(XFetchEnvelope::class, $envelope);
    }

    #[Test]
    public function test_xfetchEnvelope_oldFormatIsNotInstanceof(): void
    {
        // Old format: plain array
        $oldFormat = unserialize(serialize([['id' => 1]]));
        $this->assertNotInstanceOf(XFetchEnvelope::class, $oldFormat);
    }

    #[Test]
    public function test_xfetchEnvelope_corruptedDataIsNotInstanceof(): void
    {
        // unserialize failure returns false
        $corrupted = @unserialize('garbage');
        $this->assertNotInstanceOf(XFetchEnvelope::class, $corrupted);
    }

    // --- DaoCacheTrait constants tests ---

    #[Test]
    public function test_xfetchBetaConstantExists(): void
    {
        $this->assertTrue(
            $this->reflector->hasConstant('XFETCH_BETA'),
            'DaoCacheTrait must define XFETCH_BETA constant'
        );
        $this->assertSame(1.0, $this->reflector->getConstant('XFETCH_BETA'));
    }

    #[Test]
    public function test_xfetchMinTtlThresholdConstantExists(): void
    {
        $this->assertTrue(
            $this->reflector->hasConstant('XFETCH_MIN_TTL_THRESHOLD'),
            'DaoCacheTrait must define XFETCH_MIN_TTL_THRESHOLD constant'
        );
        $this->assertSame(10, $this->reflector->getConstant('XFETCH_MIN_TTL_THRESHOLD'));
    }

    #[Test]
    public function test_xfetchFallbackDeltaConstantExists(): void
    {
        $this->assertTrue(
            $this->reflector->hasConstant('XFETCH_FALLBACK_DELTA'),
            'DaoCacheTrait must define XFETCH_FALLBACK_DELTA constant'
        );
        $this->assertSame(0.05, $this->reflector->getConstant('XFETCH_FALLBACK_DELTA'));
    }

    #[Test]
    public function test_xfetchEnabledPropertyDefaultsTrue(): void
    {
        $this->assertTrue($this->traitUser->getXfetchEnabled());
    }

    #[Test]
    public function test_lastComputeDeltaDefaultsToZero(): void
    {
        $this->assertSame(0.0, $this->traitUser->getLastComputeDelta());
    }

    // --- δ setter tests ---

    #[Test]
    public function test_setLastComputeDelta_setsValue(): void
    {
        $this->traitUser->publicSetLastComputeDelta(0.123);
        $this->assertSame(0.123, $this->traitUser->getLastComputeDelta());
    }

    #[Test]
    public function test_setLastComputeDelta_resetToZero(): void
    {
        $this->traitUser->publicSetLastComputeDelta(0.5);
        $this->traitUser->publicSetLastComputeDelta(0.0);
        $this->assertSame(0.0, $this->traitUser->getLastComputeDelta());
    }

    // --- _shouldRecompute tests ---

    #[Test]
    public function test_shouldRecompute_returnsFalseWhenFarFromExpiry(): void
    {
        // Entry stored 10 seconds ago, TTL = 3600s, δ = 0.05s
        // Expiry is 3590 seconds away — XFetch window is ~0.05-0.25s
        $storedAt = time() - 10;
        $result = $this->traitUser->publicShouldRecompute($storedAt, 0.05, 3600);
        $this->assertFalse($result);
    }

    #[Test]
    public function test_shouldRecompute_returnsTrueWhenPastExpiry(): void
    {
        // Entry stored 3601 seconds ago with TTL = 3600s → already expired
        $storedAt = time() - 3601;
        $result = $this->traitUser->publicShouldRecompute($storedAt, 0.05, 3600);
        $this->assertTrue($result);
    }

    #[Test]
    public function test_shouldRecompute_probabilisticNearExpiry(): void
    {
        // At 0.01s before expiry with δ=1.0, probability should be very high.
        // Run 100 times; expect at least 80 triggers.
        $ttl = 3600;
        $storedAt = time() - ($ttl - 0.01);
        $triggerCount = 0;
        for ($i = 0; $i < 100; $i++) {
            if ($this->traitUser->publicShouldRecompute($storedAt, 1.0, $ttl)) {
                $triggerCount++;
            }
        }
        $this->assertGreaterThan(80, $triggerCount, "Near expiry with δ=1.0, should trigger >80% of the time");
    }

    #[Test]
    public function test_shouldRecompute_neverTriggersWithZeroDelta(): void
    {
        // δ = 0 means the window is always 0 — never triggers early
        $storedAt = time() - 3599;
        $triggerCount = 0;
        for ($i = 0; $i < 100; $i++) {
            if ($this->traitUser->publicShouldRecompute($storedAt, 0.0, 3600)) {
                $triggerCount++;
            }
        }
        $this->assertSame(0, $triggerCount, "Zero δ should never trigger early recomputation");
    }

    // --- SessionTokenStoreHandler exclusion ---

    #[Test]
    public function test_sessionTokenStoreHandler_hasXfetchDisabled(): void
    {
        $handler = new \Controller\Abstracts\Authentication\SessionTokenStoreHandler();
        $reflector = new ReflectionClass($handler);
        $prop = $reflector->getProperty('xFetchEnabled');
        $this->assertFalse($prop->getValue($handler), 'SessionTokenStoreHandler must have xFetchEnabled = false');
    }
}
