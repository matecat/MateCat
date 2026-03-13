<?php

namespace unit\Model\DataAccess;

use ArrayAccess;
use DomainException;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\ArrayAccessTrait;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

// ── Concrete helper classes ────────────────────────────────────────────────────

/**
 * Minimal concrete struct used for base AbstractDaoObjectStruct tests.
 * Exposes the protected `cachable()` method for direct testing.
 */
class ConcreteTestStruct extends AbstractDaoObjectStruct
{
    public string  $name  = '';
    public ?int    $age   = null;
    public ?string $email = null;

    public function computeCachable(string $key, callable $fn): mixed
    {
        return $this->cachable($key, $fn);
    }
}

/**
 * Concrete struct that combines AbstractDaoObjectStruct with ArrayAccessTrait.
 * This is the main subject of the ArrayAccess-specific test case.
 */
class ArrayAccessConcreteStruct extends AbstractDaoObjectStruct implements ArrayAccess
{
    use ArrayAccessTrait;

    public ?string $title       = null;
    public ?int    $count       = null;
    public ?string $description = null;

    public function computeCachable(string $key, callable $fn): mixed
    {
        return $this->cachable($key, $fn);
    }
}

// ── Test class ────────────────────────────────────────────────────────────────

/**
 * @covers \Model\DataAccess\AbstractDaoObjectStruct
 */
class AbstractDaoObjectStructTest extends AbstractTest
{
    private ConcreteTestStruct      $struct;
    private ArrayAccessConcreteStruct $arrayStruct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->struct      = new ConcreteTestStruct();
        $this->arrayStruct = new ArrayAccessConcreteStruct();
    }

    // ── __construct() ─────────────────────────────────────────────

    #[Test]
    public function constructorPopulatesPropertiesFromArray(): void
    {
        $struct = new ConcreteTestStruct(['name' => 'Alice', 'age' => 30]);

        $this->assertSame('Alice', $struct->name);
        $this->assertSame(30, $struct->age);
        $this->assertNull($struct->email);
    }

    #[Test]
    public function constructorWithEmptyArrayKeepsPropertyDefaults(): void
    {
        $struct = new ConcreteTestStruct([]);

        $this->assertSame('', $struct->name);
        $this->assertNull($struct->age);
    }

    // ── __set() ───────────────────────────────────────────────────

    #[Test]
    public function setKnownPropertyStoresTheValue(): void
    {
        $this->struct->name = 'Bob';

        $this->assertSame('Bob', $this->struct->name);
    }

    #[Test]
    public function setUnknownPropertyThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Unknown property/');

        $this->struct->undeclared = 'value';
    }

    // ── __get() ───────────────────────────────────────────────────

    #[Test]
    public function getKnownPropertyReturnsItsValue(): void
    {
        $this->struct->name = 'Charlie';

        $this->assertSame('Charlie', $this->struct->name);
    }

    #[Test]
    public function getUnknownPropertyThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Trying to get an undefined property/');

        $this->struct->undeclared;
    }

    // ── clear() ───────────────────────────────────────────────────

    #[Test]
    public function clearReturnsSameInstanceForChaining(): void
    {
        $result = $this->struct->clear();

        $this->assertSame($this->struct, $result);
    }

    // ── cachable() ────────────────────────────────────────────────

    #[Test]
    public function cachableExecutesFunctionOnFirstCallAndReturnsResult(): void
    {
        $callCount = 0;

        $result = $this->struct->computeCachable('key', function () use (&$callCount) {
            $callCount++;
            return 'computed';
        });

        $this->assertSame('computed', $result);
        $this->assertSame(1, $callCount);
    }

    #[Test]
    public function cachableReturnsCachedResultOnSubsequentCalls(): void
    {
        $callCount = 0;
        $fn        = function () use (&$callCount) {
            $callCount++;
            return 'cached_value';
        };

        $this->struct->computeCachable('key', $fn);
        $second = $this->struct->computeCachable('key', $fn);

        $this->assertSame('cached_value', $second);
        $this->assertSame(1, $callCount, 'Closure must be called exactly once');
    }

    #[Test]
    public function cachableKeysScopeIsIndependent(): void
    {
        $resultA = $this->struct->computeCachable('a', fn () => 'A');
        $resultB = $this->struct->computeCachable('b', fn () => 'B');

        $this->assertSame('A', $resultA);
        $this->assertSame('B', $resultB);
    }

    #[Test]
    public function clearInvalidatesCacheAndForcesRecomputation(): void
    {
        $callCount = 0;
        $fn        = function () use (&$callCount) {
            $callCount++;
            return 'value';
        };

        $this->struct->computeCachable('key', $fn);
        $this->struct->clear();
        $this->struct->computeCachable('key', $fn);

        $this->assertSame(2, $callCount, 'Closure must be called again after clear()');
    }

    // ── setTimestamp() ────────────────────────────────────────────

    #[Test]
    public function setTimestampFormatsValueAsIso8601(): void
    {
        $ts = mktime(12, 0, 0, 6, 15, 2023);
        $this->struct->setTimestamp('email', $ts);

        $this->assertSame(date('c', $ts), $this->struct->email);
    }

    // ── getArrayCopy() ────────────────────────────────────────────

    #[Test]
    public function getArrayCopyReturnsPublicPropertiesAsAssociativeArray(): void
    {
        $this->struct->name = 'David';
        $this->struct->age  = 25;

        $copy = $this->struct->getArrayCopy();

        $this->assertIsArray($copy);
        $this->assertSame('David', $copy['name']);
        $this->assertSame(25, $copy['age']);
        $this->assertNull($copy['email']);
    }

    // ── count() ───────────────────────────────────────────────────

    #[Test]
    public function countReturnsNumberOfPublicProperties(): void
    {
        // ConcreteTestStruct declares 3 public properties: name, age, email.
        // The protected $cached_results inherited from AbstractDaoObjectStruct is excluded.
        $this->assertSame(3, count($this->struct));
    }

    // ── ArrayAccessTrait on a concrete AbstractDaoObjectStruct ────
    //
    // The following tests cover ArrayAccessConcreteStruct, which is a concrete
    // subclass of AbstractDaoObjectStruct that additionally uses ArrayAccessTrait
    // and implements ArrayAccess.  They exercise the full interaction between
    // AbstractDaoObjectStruct's magic accessors and the trait's offset* methods.

    #[Test]
    public function offsetExistsReturnsTrueForDeclaredProperty(): void
    {
        $this->assertTrue(isset($this->arrayStruct['title']));
    }

    #[Test]
    public function offsetExistsReturnsTrueForDeclaredPropertyWithNullValue(): void
    {
        // property_exists() returns true even when the value is null
        $this->arrayStruct->title = null;
        $this->assertTrue(isset($this->arrayStruct['title']));
    }

    #[Test]
    public function offsetExistsReturnsFalseForUndeclaredProperty(): void
    {
        $this->assertFalse(isset($this->arrayStruct['nonExistent']));
    }

    #[Test]
    public function offsetGetReturnsCurrentPropertyValue(): void
    {
        $this->arrayStruct->title = 'Hello';

        $this->assertSame('Hello', $this->arrayStruct['title']);
    }

    #[Test]
    public function offsetGetOnUndeclaredPropertyThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);

        $this->arrayStruct['nonExistent'];
    }

    #[Test]
    public function offsetSetWritesValueToProperty(): void
    {
        $this->arrayStruct['title'] = 'World';

        $this->assertSame('World', $this->arrayStruct->title);
    }

    #[Test]
    public function offsetSetAndPropertyAccessAreEquivalent(): void
    {
        $this->arrayStruct['count'] = 42;

        $this->assertSame(42, $this->arrayStruct->count);
        $this->assertSame(42, $this->arrayStruct['count']);
    }

    #[Test]
    public function offsetSetOnUndeclaredPropertyThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);

        $this->arrayStruct['nonExistent'] = 'value';
    }

    #[Test]
    public function offsetUnsetSetsPropertyToNull(): void
    {
        $this->arrayStruct->title = 'ToBeUnset';
        unset($this->arrayStruct['title']);

        $this->assertNull($this->arrayStruct->title);
    }

    #[Test]
    public function offsetUnsetOnUndeclaredPropertyThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);

        unset($this->arrayStruct['nonExistent']);
    }

    #[Test]
    public function getArrayCopyAndArrayAccessReturnConsistentValues(): void
    {
        $this->arrayStruct['title']       = 'Consistency';
        $this->arrayStruct['count']       = 3;
        $this->arrayStruct['description'] = 'Test description';

        $copy = $this->arrayStruct->getArrayCopy();

        $this->assertSame($this->arrayStruct['title'],       $copy['title']);
        $this->assertSame($this->arrayStruct['count'],       $copy['count']);
        $this->assertSame($this->arrayStruct['description'], $copy['description']);
    }

    #[Test]
    public function arrayAccessStructCountReturnsNumberOfPublicProperties(): void
    {
        // ArrayAccessConcreteStruct declares 3 public properties: title, count, description.
        $this->assertSame(3, count($this->arrayStruct));
    }

    #[Test]
    public function cachableWorksCorrectlyOnArrayAccessConcreteStruct(): void
    {
        $callCount = 0;
        $fn        = function () use (&$callCount) {
            $callCount++;
            return 'cached';
        };

        $first  = $this->arrayStruct->computeCachable('key', $fn);
        $second = $this->arrayStruct->computeCachable('key', $fn);

        $this->assertSame('cached', $first);
        $this->assertSame('cached', $second);
        $this->assertSame(1, $callCount);
    }
}

