<?php

namespace unit\Model\ProjectCreation;

use Model\ProjectCreation\ProjectStructure;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

/**
 * Unit tests for the error recording pattern via {@see ProjectStructure::addError()}.
 *
 * These tests verify the integration-level contract: errors appended via addError()
 * are visible to callers reading the result['errors'] array.
 *
 * @see ProjectStructureTest for lower-level addError() unit tests
 */
class ErrorRecordingTest extends AbstractTest
{
    // =========================================================================
    // addError() — error recording
    // =========================================================================

    #[Test]
    public function testAddErrorAppendsToResultErrors(): void
    {
        $ps = new ProjectStructure();
        $ps->addError(-19, 'Invalid Upload Token.');

        $errors = $ps->result['errors'];
        $this->assertCount(1, $errors);
        $this->assertEquals(-19, $errors[0]['code']);
        $this->assertEquals('Invalid Upload Token.', $errors[0]['message']);
    }

    #[Test]
    public function testAddErrorDoesNotResetExistingErrors(): void
    {
        $ps = new ProjectStructure();
        $ps->addError(-999, 'First error');
        $ps->addError(400, 'Second error');

        $errors = $ps->result['errors'];
        $this->assertCount(2, $errors);
        $this->assertEquals(-999, $errors[0]['code']);
        $this->assertEquals(400, $errors[1]['code']);
    }

    // =========================================================================
    // Error entry structure
    // =========================================================================

    #[Test]
    public function testErrorEntryHasCodeAndMessageKeys(): void
    {
        $ps = new ProjectStructure();
        $ps->addError(-19, 'Invalid Upload Token.');

        $error = $ps->result['errors'][0];

        $this->assertArrayHasKey('code', $error);
        $this->assertArrayHasKey('message', $error);
        // Exactly two keys — no extra fields
        $this->assertCount(2, $error);
    }

    #[Test]
    public function testMultipleErrorsAreAppended(): void
    {
        $ps = new ProjectStructure();

        // First error via addError()
        $ps->addError(-19, 'Invalid Upload Token.');

        // Second error appended manually (legacy callers may still do this)
        $ps->result['errors'][] = ['code' => -999, 'message' => 'Second error'];

        $errors = $ps->result['errors'];
        $this->assertCount(2, $errors);
        $this->assertEquals(-19, $errors[0]['code']);
        $this->assertEquals(-999, $errors[1]['code']);
    }
}
