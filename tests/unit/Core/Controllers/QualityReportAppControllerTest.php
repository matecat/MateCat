<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\QualityReportControllerAPI;
use DivisionByZeroError;
use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\ExpectationFailedException;
use TypeError;

/**
 * Real-DB suite for API/App/QualityReportControllerAPI.
 *
 * This App-namespace controller adds a single one-statement delegator,
 * segments_for_ui(), that calls the (already independently tested)
 * V3 parent's segments(true). No DB seeding is required: the test only
 * needs to prove the delegation argument, so segments() itself is
 * overridden in the Testable subclass to record the call instead of
 * executing the real (DB-backed) V3 logic.
 */
class TestableQualityReportAppController extends QualityReportControllerAPI
{
    public bool $segmentsCalled = false;
    public ?bool $segmentsIsForUiArg = null;

    public function __construct()
    {
    }

    public function segments(bool $isForUI = false): void
    {
        $this->segmentsCalled = true;
        $this->segmentsIsForUiArg = $isForUI;
    }
}

class QualityReportAppControllerTest extends AbstractTest
{
    /**
     * @throws Exception
     * @throws DivisionByZeroError
     * @throws TypeError
     * @throws ExpectationFailedException
     */
    #[Test]
    public function segments_for_ui_delegates_to_segments_with_true(): void
    {
        $controller = new TestableQualityReportAppController();

        $controller->segments_for_ui();

        $this->assertTrue($controller->segmentsCalled);
        $this->assertTrue($controller->segmentsIsForUiArg);
    }
}
