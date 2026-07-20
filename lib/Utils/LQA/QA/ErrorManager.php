<?php

namespace Utils\LQA\QA;

use Utils\Tools\CatUtils;

/**
 * Manages error codes, messages, tips, and exception lists for QA checks.
 *
 * This class is responsible for:
 * - Defining all QA error codes as constants
 * - Mapping error codes to human-readable messages
 * - Mapping error codes to user-friendly tips
 * - Categorizing errors by severity (ERROR, WARNING, INFO)
 * - Tracking and reporting errors found during QA checks
 *
 * Error codes are organized in ranges:
 * - 0-30: Basic validation errors (tag count, whitespace, symbols)
 * - 1000-1199: Tag-related errors
 * - 1100-1199: Space mismatch errors
 * - 1200-1299: Symbol mismatch errors
 * - 1300-1399: BX/EX tag nesting errors
 * - 2000-2099: ICU/Smart count errors
 * - 3000+: Size restriction errors
 *
 * @package Utils\LQA\QA
 */
class ErrorManager
{
    // ========== Error Code Constants ==========

    /** @var int No error */
    public const int ERR_NONE = 0;
    public const int ERR_COUNT = 1;
    public const int ERR_SOURCE = 2;
    public const int ERR_TARGET = 3;
    public const int ERR_TAG_ID = 4;
    public const int ERR_WS_HEAD = 5;
    public const int ERR_WS_TAIL = 6;
    public const int ERR_TAB_HEAD = 7;
    public const int ERR_TAB_TAIL = 8;
    public const int ERR_CR_HEAD = 9;
    public const int ERR_CR_TAIL = 10;
    public const int ERR_BOUNDARY_HEAD = 11;
    public const int ERR_BOUNDARY_TAIL = 12;
    public const int ERR_UNCLOSED_X_TAG = 13;
    public const int ERR_BOUNDARY_HEAD_TEXT = 14;
    public const int ERR_TAG_ORDER = 15;
    public const int ERR_NEWLINE_MISMATCH = 16;
    public const int ERR_DOLLAR_MISMATCH = 17;
    public const int ERR_AMPERSAND_MISMATCH = 18;
    public const int ERR_AT_MISMATCH = 19;
    public const int ERR_HASH_MISMATCH = 20;
    public const int ERR_POUNDSIGN_MISMATCH = 21;
    public const int ERR_PERCENT_MISMATCH = 22;
    public const int ERR_EQUALSIGN_MISMATCH = 23;
    public const int ERR_TAB_MISMATCH = 24;
    public const int ERR_STARSIGN_MISMATCH = 25;
    public const int ERR_GLOSSARY_MISMATCH = 26;
    public const int ERR_SPECIAL_ENTITY_MISMATCH = 27;
    public const int ERR_EUROSIGN_MISMATCH = 28;
    public const int ERR_UNCLOSED_G_TAG = 29;
    public const int ERR_ICU_VALIDATION = 30;

    public const int ERR_TAG_MISMATCH = 1000;
    public const int ERR_SPACE_MISMATCH = 1100;
    public const int ERR_SPACE_MISMATCH_TEXT = 1101;
    public const int ERR_BOUNDARY_HEAD_SPACE_MISMATCH = 1102;
    public const int ERR_BOUNDARY_TAIL_SPACE_MISMATCH = 1103;
    public const int ERR_SPACE_MISMATCH_AFTER_TAG = 1104;
    public const int ERR_SPACE_MISMATCH_BEFORE_TAG = 1105;
    public const int ERR_SYMBOL_MISMATCH = 1200;
    public const int ERR_EX_BX_NESTED_IN_G = 1300;
    public const int ERR_EX_BX_WRONG_POSITION = 1301;
    public const int ERR_EX_BX_COUNT_MISMATCH = 1302;
    public const int SMART_COUNT_PLURAL_MISMATCH = 2000;
    public const int SMART_COUNT_MISMATCH = 2001;
    public const int ERR_SIZE_RESTRICTION = 3000;

    /** @var int Fuzzy TM match confirmed without any modification */
    public const int ERR_FUZZY_UNCHANGED = 4000;

    public const string ERROR = 'ERROR';
    public const string WARNING = 'WARNING';
    public const string INFO = 'INFO';

    /** @var array<int, string|null> */
    protected array $errorMap = [
        0 => '',
        1 => 'Tag count mismatch',
        2 => 'bad source xml',
        3 => 'bad target xml',
        4 => 'Tag ID mismatch: Check and edit tags with differing IDs.',
        5 => 'Heading whitespaces mismatch',
        6 => 'Tail whitespaces mismatch',
        7 => 'Heading tab mismatch',
        8 => 'Tail tab mismatch',
        9 => 'Heading carriage return mismatch',
        10 => 'Tail carriage return mismatch',
        11 => 'Char mismatch between tags',
        12 => 'End line char mismatch',
        13 => 'Wrong format for x tag. Should be < x .... />',
        14 => 'Char mismatch before a tag',
        15 => 'Tag order mismatch',
        16 => 'New line mismatch',
        17 => 'Dollar sign mismatch',
        18 => 'Ampersand sign mismatch',
        19 => 'At sign mismatch',
        20 => 'Hash sign mismatch',
        21 => 'Pound sign mismatch',
        22 => 'Percent sign mismatch',
        23 => 'Equalsign sign mismatch',
        24 => 'Tab sign mismatch',
        25 => 'Star sign mismatch',
        26 => 'Glossary mismatch',
        27 => 'Special char entity mismatch',
        29 => 'File-breaking tag issue',
        30 => 'ICU message issue',

        1000 => 'Tag mismatch.',
        1100 => 'More/fewer whitespaces found next to the tags.',
        1101 => 'More/fewer whitespaces found in the text.',
        1102 => 'Leading space in target not corresponding to source.',
        1103 => 'Trailing space in target not corresponding to source.',
        1104 => 'Whitespace(s) mismatch AFTER a tag.',
        1105 => 'Whitespace(s) mismatch BEFORE a tag.',
        1200 => 'Symbol mismatch',
        1300 => 'Found nested <ex> and/or <bx> tag(s) inside a <g> tag',
        1301 => 'Wrong <ex> and/or <bx> placement',
        1302 => '<ex>, <bx> and/or <g> total count mismatch',
        2000 => 'Smart count plural forms mismatch',
        2001 => '%smartcount tag count mismatch',
        3000 => 'Characters limit exceeded',
        4000 => 'Fuzzy match confirmed without changes',
    ];

    /** @var array<int, string|null> */
    protected array $tipMap = [
        29 => "Should be < g ... > ... < /g >",
        1000 => "Press 'alt + t' shortcut to add tags or delete extra tags.",
        3000 => 'Maximum characters limit exceeded.',
        4000 => 'A fuzzy TM match was confirmed without any edit. Please review it before confirming.',
    ];

    /** @var array{ERROR: list<ErrObject>, WARNING: list<ErrObject>, INFO: list<ErrObject>} */
    protected array $exceptionList = [
        self::ERROR => [],
        self::WARNING => [],
        self::INFO => []
    ];

    protected ?string $sourceSegLang = null;

    public function setSourceSegLang(?string $lang): void
    {
        $this->sourceSegLang = $lang;
    }

    /**
     * Add a custom error to the error map
     *
     * @param array{code: int, debug?: string|null, tip?: string|null} $errorMap
     */
    public function addCustomError(array $errorMap): void
    {
        $this->errorMap[$errorMap['code']] = $errorMap['debug'] ?? null;
        $this->tipMap[$errorMap['code']] = $errorMap['tip'] ?? null;
    }

    public function setErrorMessage(int $errorCode, string $message): void
    {
        $this->errorMap[$errorCode] = $message;
    }

    protected function getTipValue(int $errorID): ?string
    {
        return $this->tipMap[$errorID] ?? null;
    }

    public function getErrorMessage(int $errorCode): string
    {
        return $this->errorMap[$errorCode] ?? '';
    }

    /**
     * Routes an input error code to a [severity bucket, canonical outcome code] pair.
     *
     * Grouped codes intentionally collapse onto a shared outcome (e.g. every symbol code reports
     * ERR_SYMBOL_MISMATCH, and ERR_COUNT/ERR_SOURCE/ERR_TARGET report ERR_TAG_MISMATCH). Codes that
     * are absent from this table fall back to [WARNING, <the code itself>] (see {@see self::addError()}).
     *
     * @var array<int, array{0: self::ERROR|self::WARNING|self::INFO, 1: int}>
     */
    private const array ERROR_ROUTING = [
        // --- ERROR ---
        self::ERR_COUNT                     => [self::ERROR, self::ERR_TAG_MISMATCH],
        self::ERR_SOURCE                    => [self::ERROR, self::ERR_TAG_MISMATCH],
        self::ERR_TARGET                    => [self::ERROR, self::ERR_TAG_MISMATCH],
        self::ERR_TAG_MISMATCH              => [self::ERROR, self::ERR_TAG_MISMATCH],
        self::ERR_TAG_ID                    => [self::ERROR, self::ERR_TAG_ID],
        self::ERR_EX_BX_COUNT_MISMATCH      => [self::ERROR, self::ERR_EX_BX_COUNT_MISMATCH],
        self::ERR_EX_BX_NESTED_IN_G         => [self::ERROR, self::ERR_EX_BX_NESTED_IN_G],
        self::ERR_UNCLOSED_X_TAG            => [self::ERROR, self::ERR_UNCLOSED_X_TAG],
        self::ERR_UNCLOSED_G_TAG            => [self::ERROR, self::ERR_UNCLOSED_G_TAG],
        self::SMART_COUNT_PLURAL_MISMATCH   => [self::ERROR, self::SMART_COUNT_PLURAL_MISMATCH],
        self::SMART_COUNT_MISMATCH          => [self::ERROR, self::SMART_COUNT_MISMATCH],
        self::ERR_SIZE_RESTRICTION          => [self::ERROR, self::ERR_SIZE_RESTRICTION],
        self::ERR_ICU_VALIDATION            => [self::ERROR, self::ERR_ICU_VALIDATION],

        // --- WARNING ---
        self::ERR_EX_BX_WRONG_POSITION      => [self::WARNING, self::ERR_EX_BX_WRONG_POSITION],

        // --- INFO ---
        self::ERR_WS_HEAD                   => [self::INFO, self::ERR_SPACE_MISMATCH_TEXT],
        self::ERR_WS_TAIL                   => [self::INFO, self::ERR_SPACE_MISMATCH_TEXT],
        self::ERR_TAB_HEAD                  => [self::INFO, self::ERR_TAB_MISMATCH],
        self::ERR_TAB_TAIL                  => [self::INFO, self::ERR_TAB_MISMATCH],
        self::ERR_BOUNDARY_HEAD             => [self::INFO, self::ERR_BOUNDARY_HEAD_SPACE_MISMATCH],
        // NB: emitted only for non-CJ source languages; the CJ guard lives in addError().
        self::ERR_BOUNDARY_TAIL             => [self::INFO, self::ERR_BOUNDARY_TAIL_SPACE_MISMATCH],
        self::ERR_SPACE_MISMATCH_AFTER_TAG  => [self::INFO, self::ERR_SPACE_MISMATCH_AFTER_TAG],
        self::ERR_SPACE_MISMATCH_BEFORE_TAG => [self::INFO, self::ERR_SPACE_MISMATCH_BEFORE_TAG],
        self::ERR_BOUNDARY_HEAD_TEXT        => [self::INFO, self::ERR_SPACE_MISMATCH],
        self::ERR_DOLLAR_MISMATCH           => [self::INFO, self::ERR_SYMBOL_MISMATCH],
        self::ERR_AMPERSAND_MISMATCH        => [self::INFO, self::ERR_SYMBOL_MISMATCH],
        self::ERR_AT_MISMATCH               => [self::INFO, self::ERR_SYMBOL_MISMATCH],
        self::ERR_HASH_MISMATCH             => [self::INFO, self::ERR_SYMBOL_MISMATCH],
        self::ERR_POUNDSIGN_MISMATCH        => [self::INFO, self::ERR_SYMBOL_MISMATCH],
        self::ERR_EUROSIGN_MISMATCH         => [self::INFO, self::ERR_SYMBOL_MISMATCH],
        self::ERR_PERCENT_MISMATCH          => [self::INFO, self::ERR_SYMBOL_MISMATCH],
        self::ERR_EQUALSIGN_MISMATCH        => [self::INFO, self::ERR_SYMBOL_MISMATCH],
        self::ERR_TAB_MISMATCH              => [self::INFO, self::ERR_SYMBOL_MISMATCH],
        self::ERR_STARSIGN_MISMATCH         => [self::INFO, self::ERR_SYMBOL_MISMATCH],
        self::ERR_SPECIAL_ENTITY_MISMATCH   => [self::INFO, self::ERR_SYMBOL_MISMATCH],
        self::ERR_SYMBOL_MISMATCH           => [self::INFO, self::ERR_SYMBOL_MISMATCH],
        self::ERR_NEWLINE_MISMATCH          => [self::INFO, self::ERR_NEWLINE_MISMATCH],
    ];

    /**
     * Add an error to the exception list based on its error code.
     *
     * The code is mapped to a [severity bucket, canonical outcome] pair via {@see self::ERROR_ROUTING};
     * unlisted codes fall back to [WARNING, <the code itself>]. Two codes are special-cased: ERR_NONE is
     * a no-op, and ERR_BOUNDARY_TAIL is suppressed for CJ (Chinese/Japanese) source languages.
     */
    public function addError(int $errCode): void
    {
        if ($errCode === self::ERR_NONE) {
            return;
        }

        // If the source segment is CJ we don't add a trailing-space mismatch error.
        if ($errCode === self::ERR_BOUNDARY_TAIL && false !== CatUtils::isCJ($this->sourceSegLang)) {
            return;
        }

        [$level, $outcome] = self::ERROR_ROUTING[$errCode] ?? [self::WARNING, $errCode];

        $this->exceptionList[$level][] = ErrObject::get([
            'outcome' => $outcome,
            'debug'   => $this->errorMap[$outcome] ?? null,
            'tip'     => $this->getTipValue($outcome)
        ]);
    }

    protected function hasErrorsAtLevel(string $level): bool
    {
        return match ($level) {
            self::ERROR => !empty($this->exceptionList[self::ERROR]),
            self::WARNING => !empty(array_merge($this->exceptionList[self::ERROR], $this->exceptionList[self::WARNING])),
            self::INFO => !empty(array_merge($this->exceptionList[self::INFO], $this->exceptionList[self::ERROR], $this->exceptionList[self::WARNING])),
            default => false,
        };
    }

    /**
     * @return array{ERROR: list<ErrObject>, WARNING: list<ErrObject>, INFO: list<ErrObject>}
     */
    public function getExceptionList(): array
    {
        return $this->exceptionList;
    }

    public function thereAreErrors(): bool
    {
        return $this->hasErrorsAtLevel(self::ERROR);
    }

    public function thereAreWarnings(): bool
    {
        return $this->hasErrorsAtLevel(self::WARNING);
    }

    public function thereAreNotices(): bool
    {
        return $this->hasErrorsAtLevel(self::INFO);
    }

    /**
     * @return ErrObject[]
     */
    public function getErrors(): array
    {
        return $this->getErrorsByLevel();
    }

    public function getErrorsJSON(): string
    {
        return json_encode($this->getErrorsByLevel(self::ERROR, true)) ?: '[]';
    }

    /**
     * @return ErrObject[]
     */
    public function getWarnings(): array
    {
        return $this->getErrorsByLevel(self::WARNING);
    }

    public function getWarningsJSON(): string
    {
        return json_encode($this->getErrorsByLevel(self::WARNING, true)) ?: '[]';
    }

    /**
     * @return ErrObject[]
     */
    public function getNotices(): array
    {
        return $this->getErrorsByLevel(self::INFO);
    }

    public function getNoticesJSON(): string
    {
        return json_encode($this->getErrorsByLevel(self::INFO)) ?: '[]';
    }

    /**
     * @return ErrObject[]
     */
    protected function getErrorsByLevel(string $level = self::ERROR, bool $count = false): array
    {
        if (!$this->hasErrorsAtLevel($level)) {
            return [
                ErrObject::get([
                    'outcome' => self::ERR_NONE,
                    'debug' => $this->errorMap[self::ERR_NONE] . " [ 0 ]"
                ])
            ];
        }

        $list = match ($level) {
            self::INFO => array_merge($this->exceptionList[self::INFO], $this->exceptionList[self::WARNING], $this->exceptionList[self::ERROR]),
            self::WARNING => array_merge($this->exceptionList[self::WARNING], $this->exceptionList[self::ERROR]),
            default => $this->exceptionList[self::ERROR],
        };

        if ($count) {
            $errorCount = array_count_values(array_map('strval', $list));
            $list = array_values(array_unique($list));

            foreach ($list as $errObj) {
                $outcomeKey = (string)$errObj->outcome;
                $errObj->debug = $errObj->getOrigDebug() . " ( " . ($errorCount[$outcomeKey] ?? 1) . " )";
            }
        }

        return $list;
    }

    /**
     * Parse JSON error string and populate the exception list
     *
     * @return array{ERROR: list<ErrObject>, WARNING: list<ErrObject>, INFO: list<ErrObject>}
     */
    public static function JSONtoExceptionList(string $jsonString): array
    {
        // Create a new instance of the current class
        $manager = new self();

        // If the JSON string is malformed, attempt to extract error codes via regex fallback
        // This happens when the error JSON is longer than 512 bytes,
        // which is the maximum allowed by the field in mysql table segment_translations
        // Extract the first outcome code found in the JSON string
        if (!json_validate($jsonString)) {
            // Try to extract the "outcome" numeric value from the invalid JSON
            preg_match('/"outcome":\s*?(\d+),/', $jsonString, $matches);
            if (!empty($matches)) {
                // Register the extracted error code, defaulting to ERR_TAG_MISMATCH if the capture group is missing (conservative)
                $manager->addError((int)$matches[1]);
            }
        } else {
            // Parse valid JSON into an associative array
            $jsonValue = json_decode($jsonString, true);
            if (is_array($jsonValue)) {
                // Iterate over each error entry and register its outcome code
                foreach ($jsonValue as $errArray) {
                    $manager->addError((int)($errArray['outcome'] ?? self::ERR_TAG_MISMATCH));
                }
            }
        }
        return $manager->exceptionList;
    }
}

