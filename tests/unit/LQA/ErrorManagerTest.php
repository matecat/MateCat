<?php

namespace unit\LQA;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\LQA\QA\ErrorManager;

class ErrorManagerTest extends AbstractTest
{
    private ErrorManager $errorManager;

    public function setUp(): void
    {
        parent::setUp();
        $this->errorManager = new ErrorManager();
    }

    // ========== Constants Tests ==========

    #[Test]
    public function errorConstants(): void
    {
        $this->assertEquals(0, ErrorManager::ERR_NONE);
        $this->assertEquals(1, ErrorManager::ERR_COUNT);
        $this->assertEquals(2, ErrorManager::ERR_SOURCE);
        $this->assertEquals(3, ErrorManager::ERR_TARGET);
        $this->assertEquals(4, ErrorManager::ERR_TAG_ID);
        $this->assertEquals(1000, ErrorManager::ERR_TAG_MISMATCH);
        $this->assertEquals(1100, ErrorManager::ERR_SPACE_MISMATCH);
        $this->assertEquals(1200, ErrorManager::ERR_SYMBOL_MISMATCH);
        $this->assertEquals(1300, ErrorManager::ERR_EX_BX_NESTED_IN_G);
        $this->assertEquals(2000, ErrorManager::SMART_COUNT_PLURAL_MISMATCH);
        $this->assertEquals(3000, ErrorManager::ERR_SIZE_RESTRICTION);
    }

    #[Test]
    public function severityConstants(): void
    {
        $this->assertEquals('ERROR', ErrorManager::ERROR);
        $this->assertEquals('WARNING', ErrorManager::WARNING);
        $this->assertEquals('INFO', ErrorManager::INFO);
    }

    // ========== Add Error Tests - Error Level ==========

    #[Test]
    public function addErrorNone(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_NONE);

        $this->assertFalse($this->errorManager->thereAreErrors());
        $this->assertFalse($this->errorManager->thereAreWarnings());
        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function addErrorCount(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_COUNT);

        $this->assertTrue($this->errorManager->thereAreErrors());
        $errors = $this->errorManager->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(ErrorManager::ERR_TAG_MISMATCH, $errors[0]->outcome);
    }

    #[Test]
    public function addErrorSource(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_SOURCE);

        $this->assertTrue($this->errorManager->thereAreErrors());
        $errors = $this->errorManager->getErrors();
        $this->assertEquals(ErrorManager::ERR_TAG_MISMATCH, $errors[0]->outcome);
    }

    #[Test]
    public function addErrorTarget(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_TARGET);

        $this->assertTrue($this->errorManager->thereAreErrors());
        $errors = $this->errorManager->getErrors();
        $this->assertEquals(ErrorManager::ERR_TAG_MISMATCH, $errors[0]->outcome);
    }

    #[Test]
    public function addErrorTagMismatch(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_TAG_MISMATCH);

        $this->assertTrue($this->errorManager->thereAreErrors());
        $errors = $this->errorManager->getErrors();
        $this->assertEquals(ErrorManager::ERR_TAG_MISMATCH, $errors[0]->outcome);
        $this->assertEquals('Tag mismatch.', $errors[0]->debug);
    }

    #[Test]
    public function addErrorTagId(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_TAG_ID);

        $this->assertTrue($this->errorManager->thereAreErrors());
        $errors = $this->errorManager->getErrors();
        $this->assertEquals(ErrorManager::ERR_TAG_ID, $errors[0]->outcome);
    }

    #[Test]
    public function addErrorExBxCountMismatch(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_EX_BX_COUNT_MISMATCH);

        $this->assertTrue($this->errorManager->thereAreErrors());
        $errors = $this->errorManager->getErrors();
        $this->assertEquals(ErrorManager::ERR_EX_BX_COUNT_MISMATCH, $errors[0]->outcome);
    }

    #[Test]
    public function addErrorExBxNestedInG(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_EX_BX_NESTED_IN_G);

        $this->assertTrue($this->errorManager->thereAreErrors());
        $errors = $this->errorManager->getErrors();
        $this->assertEquals(ErrorManager::ERR_EX_BX_NESTED_IN_G, $errors[0]->outcome);
    }

    #[Test]
    public function addErrorUnclosedXTag(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_UNCLOSED_X_TAG);

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function addErrorUnclosedGTag(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_UNCLOSED_G_TAG);

        $this->assertTrue($this->errorManager->thereAreErrors());
        $errors = $this->errorManager->getErrors();
        $this->assertEquals(ErrorManager::ERR_UNCLOSED_G_TAG, $errors[0]->outcome);
    }

    #[Test]
    public function addErrorSmartCountPluralMismatch(): void
    {
        $this->errorManager->addError(ErrorManager::SMART_COUNT_PLURAL_MISMATCH);

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function addErrorSmartCountMismatch(): void
    {
        $this->errorManager->addError(ErrorManager::SMART_COUNT_MISMATCH);

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    #[Test]
    public function addErrorSizeRestriction(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_SIZE_RESTRICTION);

        $this->assertTrue($this->errorManager->thereAreErrors());
        $errors = $this->errorManager->getErrors();
        $this->assertEquals(ErrorManager::ERR_SIZE_RESTRICTION, $errors[0]->outcome);
    }

    #[Test]
    public function addErrorIcuValidation(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_ICU_VALIDATION);

        $this->assertTrue($this->errorManager->thereAreErrors());
    }

    // ========== Add Error Tests - Warning Level ==========

    #[Test]
    public function addErrorExBxWrongPosition(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_EX_BX_WRONG_POSITION);

        $this->assertFalse($this->errorManager->thereAreErrors());
        $this->assertTrue($this->errorManager->thereAreWarnings());
        $warnings = $this->errorManager->getWarnings();
        $this->assertEquals(ErrorManager::ERR_EX_BX_WRONG_POSITION, $warnings[0]->outcome);
    }

    #[Test]
    public function addErrorTagOrder(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_TAG_ORDER);

        $this->assertFalse($this->errorManager->thereAreErrors());
        $this->assertTrue($this->errorManager->thereAreWarnings());
    }

    // ========== Add Error Tests - Info Level ==========

    #[Test]
    public function addErrorWsHead(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_WS_HEAD);

        $this->assertFalse($this->errorManager->thereAreErrors());
        $this->assertFalse($this->errorManager->thereAreWarnings());
        $this->assertTrue($this->errorManager->thereAreNotices());
        $notices = $this->errorManager->getNotices();
        $this->assertEquals(ErrorManager::ERR_SPACE_MISMATCH_TEXT, $notices[0]->outcome);
    }

    #[Test]
    public function addErrorWsTail(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_WS_TAIL);

        $this->assertTrue($this->errorManager->thereAreNotices());
        $notices = $this->errorManager->getNotices();
        $this->assertEquals(ErrorManager::ERR_SPACE_MISMATCH_TEXT, $notices[0]->outcome);
    }

    #[Test]
    public function addErrorTabHead(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_TAB_HEAD);

        $this->assertTrue($this->errorManager->thereAreNotices());
        $notices = $this->errorManager->getNotices();
        $this->assertEquals(ErrorManager::ERR_TAB_MISMATCH, $notices[0]->outcome);
    }

    #[Test]
    public function addErrorTabTail(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_TAB_TAIL);

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function addErrorBoundaryHead(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_BOUNDARY_HEAD);

        $this->assertTrue($this->errorManager->thereAreNotices());
        $notices = $this->errorManager->getNotices();
        $this->assertEquals(ErrorManager::ERR_BOUNDARY_HEAD_SPACE_MISMATCH, $notices[0]->outcome);
    }

    #[Test]
    public function addErrorBoundaryTailNonCJ(): void
    {
        $this->errorManager->setSourceSegLang('en-US');
        $this->errorManager->addError(ErrorManager::ERR_BOUNDARY_TAIL);

        $this->assertTrue($this->errorManager->thereAreNotices());
        $notices = $this->errorManager->getNotices();
        $this->assertEquals(ErrorManager::ERR_BOUNDARY_TAIL_SPACE_MISMATCH, $notices[0]->outcome);
    }

    #[Test]
    public function addErrorBoundaryTailCJSource(): void
    {
        // CJ languages should not add trailing space mismatch error
        $this->errorManager->setSourceSegLang('ja-JP');
        $this->errorManager->addError(ErrorManager::ERR_BOUNDARY_TAIL);

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function addErrorBoundaryTailChineseSource(): void
    {
        $this->errorManager->setSourceSegLang('zh-CN');
        $this->errorManager->addError(ErrorManager::ERR_BOUNDARY_TAIL);

        $this->assertFalse($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function addErrorSpaceMismatchAfterTag(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_SPACE_MISMATCH_AFTER_TAG);

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function addErrorSpaceMismatchBeforeTag(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_SPACE_MISMATCH_BEFORE_TAG);

        $this->assertTrue($this->errorManager->thereAreNotices());
    }

    #[Test]
    public function addErrorBoundaryHeadText(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_BOUNDARY_HEAD_TEXT);

        $this->assertTrue($this->errorManager->thereAreNotices());
        $notices = $this->errorManager->getNotices();
        $this->assertEquals(ErrorManager::ERR_SPACE_MISMATCH, $notices[0]->outcome);
    }

    #[Test]
    public function addSymbolMismatchErrors(): void
    {
        $symbolErrors = [
            ErrorManager::ERR_DOLLAR_MISMATCH,
            ErrorManager::ERR_AMPERSAND_MISMATCH,
            ErrorManager::ERR_AT_MISMATCH,
            ErrorManager::ERR_HASH_MISMATCH,
            ErrorManager::ERR_POUNDSIGN_MISMATCH,
            ErrorManager::ERR_EUROSIGN_MISMATCH,
            ErrorManager::ERR_PERCENT_MISMATCH,
            ErrorManager::ERR_EQUALSIGN_MISMATCH,
            ErrorManager::ERR_TAB_MISMATCH,
            ErrorManager::ERR_STARSIGN_MISMATCH,
            ErrorManager::ERR_SPECIAL_ENTITY_MISMATCH,
            ErrorManager::ERR_SYMBOL_MISMATCH,
        ];

        foreach ($symbolErrors as $errCode) {
            $manager = new ErrorManager();
            $manager->addError($errCode);

            $this->assertTrue($manager->thereAreNotices(), "Error code $errCode should produce a notice");
            $notices = $manager->getNotices();
            $this->assertEquals(ErrorManager::ERR_SYMBOL_MISMATCH, $notices[0]->outcome);
        }
    }

    #[Test]
    public function addErrorNewlineMismatch(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_NEWLINE_MISMATCH);

        $this->assertTrue($this->errorManager->thereAreNotices());
        $notices = $this->errorManager->getNotices();
        $this->assertEquals(ErrorManager::ERR_NEWLINE_MISMATCH, $notices[0]->outcome);
    }

    // ========== Exception List Tests ==========

    #[Test]
    public function getExceptionList(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_TAG_MISMATCH);
        $this->errorManager->addError(ErrorManager::ERR_TAG_ORDER);
        $this->errorManager->addError(ErrorManager::ERR_WS_HEAD);

        $list = $this->errorManager->getExceptionList();

        $this->assertArrayHasKey(ErrorManager::ERROR, $list);
        $this->assertArrayHasKey(ErrorManager::WARNING, $list);
        $this->assertArrayHasKey(ErrorManager::INFO, $list);

        $this->assertCount(1, $list[ErrorManager::ERROR]);
        $this->assertCount(1, $list[ErrorManager::WARNING]);
        $this->assertCount(1, $list[ErrorManager::INFO]);
    }

    // ========== Get Errors/Warnings/Notices JSON Tests ==========

    #[Test]
    public function getErrorsJSONWithErrors(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_TAG_MISMATCH);
        $this->errorManager->addError(ErrorManager::ERR_TAG_MISMATCH);

        $json = $this->errorManager->getErrorsJSON();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded); // Should be unique
        $this->assertEquals(ErrorManager::ERR_TAG_MISMATCH, $decoded[0]['outcome']);
        $this->assertStringContainsString('( 2 )', $decoded[0]['debug']); // Count in parentheses
    }

    #[Test]
    public function getErrorsJSONWithNoErrors(): void
    {
        $json = $this->errorManager->getErrorsJSON();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertEquals(ErrorManager::ERR_NONE, $decoded[0]['outcome']);
    }

    #[Test]
    public function getWarningsJSON(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_TAG_ORDER);
        $this->errorManager->addError(ErrorManager::ERR_TAG_ORDER);

        $json = $this->errorManager->getWarningsJSON();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertStringContainsString('( 2 )', $decoded[0]['debug']);
    }

    #[Test]
    public function getNoticesJSON(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_WS_HEAD);

        $json = $this->errorManager->getNoticesJSON();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);
    }

    // ========== Custom Error Tests ==========

    #[Test]
    public function addCustomError(): void
    {
        $customError = [
            'code' => 9999,
            'debug' => 'Custom error message',
            'tip' => 'Custom tip message'
        ];

        $this->errorManager->addCustomError($customError);
        $this->errorManager->addError(9999);

        $this->assertTrue($this->errorManager->thereAreWarnings()); // Unknown errors go to WARNING
    }

    #[Test]
    public function setErrorMessage(): void
    {
        $this->errorManager->setErrorMessage(ErrorManager::ERR_TAG_MISMATCH, 'Modified tag error');
        $this->errorManager->addError(ErrorManager::ERR_TAG_MISMATCH);

        $errors = $this->errorManager->getErrors();
        $this->assertEquals('Modified tag error', $errors[0]->debug);
    }

    #[Test]
    public function getErrorMessage(): void
    {
        $message = $this->errorManager->getErrorMessage(ErrorManager::ERR_TAG_MISMATCH);
        $this->assertEquals('Tag mismatch.', $message);

        $message = $this->errorManager->getErrorMessage(999999); // Non-existent
        $this->assertEquals('', $message);
    }

    // ========== JSON to Exception List Tests ==========

    #[Test]
    public function jSONtoExceptionList(): void
    {
        $json = '[{"outcome": 1000, "debug": "Tag mismatch."}, {"outcome": 15, "debug": "Tag order mismatch"}]';

        $list = ErrorManager::JSONtoExceptionList($json);

        $this->assertArrayHasKey(ErrorManager::ERROR, $list);
        $this->assertArrayHasKey(ErrorManager::WARNING, $list);
        $this->assertArrayHasKey(ErrorManager::INFO, $list);
        $this->assertCount(1, $list[ErrorManager::ERROR]);
        $this->assertCount(1, $list[ErrorManager::WARNING]);
    }

    #[Test]
    public function invalidJSON(): void
    {
        $json = '[{"outcome": 1000, "debug": "Tag mismatch."}, {"outcome": 15, ';

        $list = ErrorManager::JSONtoExceptionList($json);

        $this->assertArrayHasKey(ErrorManager::ERROR, $list);
        $this->assertArrayHasKey(ErrorManager::WARNING, $list);
        $this->assertArrayHasKey(ErrorManager::INFO, $list);
        $this->assertCount(1, $list[ErrorManager::ERROR]);
        $this->assertCount(0, $list[ErrorManager::WARNING]);
    }

    #[Test]
    public function jSONtoExceptionListWithInvalidJson(): void
    {
        $json = 'invalid json';

        $list = ErrorManager::JSONtoExceptionList($json);

        $this->assertEmpty($list[ErrorManager::ERROR]);
        $this->assertEmpty($list[ErrorManager::WARNING]);
        $this->assertEmpty($list[ErrorManager::INFO]);
    }

    #[Test]
    public function jSONtoExceptionListWithEmptyArray(): void
    {
        $json = '[]';

        $list = ErrorManager::JSONtoExceptionList($json);

        $this->assertEmpty($list[ErrorManager::ERROR]);
    }

    // ========== Multiple Errors Tests ==========

    #[Test]
    public function multipleErrorsOfSameType(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_TAG_MISMATCH);
        $this->errorManager->addError(ErrorManager::ERR_TAG_MISMATCH);
        $this->errorManager->addError(ErrorManager::ERR_TAG_MISMATCH);

        $errors = $this->errorManager->getErrors();
        $this->assertCount(3, $errors);
    }

    #[Test]
    public function warningsIncludeErrors(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_TAG_MISMATCH); // Error
        $this->errorManager->addError(ErrorManager::ERR_TAG_ORDER);    // Warning

        $warnings = $this->errorManager->getWarnings();
        $this->assertCount(2, $warnings);
    }

    #[Test]
    public function noticesIncludeWarningsAndErrors(): void
    {
        $this->errorManager->addError(ErrorManager::ERR_TAG_MISMATCH); // Error
        $this->errorManager->addError(ErrorManager::ERR_TAG_ORDER);    // Warning
        $this->errorManager->addError(ErrorManager::ERR_WS_HEAD);      // Info

        $notices = $this->errorManager->getNotices();
        $this->assertCount(3, $notices);
    }
}

