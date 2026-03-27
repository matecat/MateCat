<?php

namespace unit\DAO;

use Model\DataAccess\XFetchEnvelope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Tests for the XFetch probabilistic early expiration algorithm
 * implemented in DaoCacheTrait.
 */
class XFetchAlgorithmTest extends TestCase
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
                return $this->xfetchEnabled;
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
}
