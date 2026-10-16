<?php

namespace Matecat\Core\Model\ProjectCreation;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\ProjectCreation\ProjectCreationError;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::describeCreationFailure()}.
 *
 * Verifies:
 * - An exception carrying a ProjectCreationError code keeps its code and message
 * - Code 0, an int code outside the enum, and a non-int (SQLSTATE) code all collapse to the
 *   generic CREATION_FAILED error, so an internal message never reaches the user
 */
class DescribeCreationFailureTest extends AbstractTest
{
    private const string GENERIC_MESSAGE = 'Project creation failed. Please try again.';

    private TestableProjectManager $pm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function enumCodePassesThroughWithItsMessage(): void
    {
        $e = new Exception('Zip storage failed', ProjectCreationError::ZIP_STORE_FAILED->value);

        $this->assertSame(
            [ProjectCreationError::ZIP_STORE_FAILED->value, 'Zip storage failed'],
            $this->pm->callDescribeCreationFailure($e)
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function positiveEnumCodePassesThroughWithItsMessage(): void
    {
        $e = new Exception('Too many words', ProjectCreationError::MAX_WORDS_EXCEEDED->value);

        $this->assertSame(
            [ProjectCreationError::MAX_WORDS_EXCEEDED->value, 'Too many words'],
            $this->pm->callDescribeCreationFailure($e)
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function codeZeroGivesTheGenericError(): void
    {
        $e = new RuntimeException('Internal detail that must stay in the log');

        $this->assertSame(
            [ProjectCreationError::CREATION_FAILED->value, self::GENERIC_MESSAGE],
            $this->pm->callDescribeCreationFailure($e)
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function unknownIntCodeGivesTheGenericError(): void
    {
        $e = new Exception('Some library failure', 12345);

        $this->assertSame(
            [ProjectCreationError::CREATION_FAILED->value, self::GENERIC_MESSAGE],
            $this->pm->callDescribeCreationFailure($e)
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function pdoExceptionWithSqlStateCodeGivesTheGenericError(): void
    {
        $e = new PDOException('SQLSTATE[HY000]: General error');
        (new ReflectionProperty(Exception::class, 'code'))->setValue($e, 'HY000');

        $this->assertSame('HY000', $e->getCode());
        $this->assertSame(
            [ProjectCreationError::CREATION_FAILED->value, self::GENERIC_MESSAGE],
            $this->pm->callDescribeCreationFailure($e)
        );
    }
}
