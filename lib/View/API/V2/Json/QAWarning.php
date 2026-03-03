<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 04/09/2018
 * Time: 12:40
 */

namespace View\API\V2\Json;

use Utils\LQA\QA;

class QAWarning
{

    protected array $structure;

    const string GLOSSARY_CATEGORY = "GLOSSARY";
    const string TAGS_CATEGORY = "TAGS";
    const string SIZE_CATEGORY = "SIZE";
    const string MISMATCH_CATEGORY = "MISMATCH";
    const string ICU_CATEGORY = "ICU";

    protected function pushErrorSegment($error_type, $error_category, $content): void
    {
        $category = match ($error_category) {
            QA::ERR_SIZE_RESTRICTION => self::SIZE_CATEGORY,
            QA::ERR_SPACE_MISMATCH_TEXT,
            QA::ERR_TAB_MISMATCH,
            QA::ERR_SPACE_MISMATCH,
            QA::ERR_BOUNDARY_HEAD_SPACE_MISMATCH,
            QA::ERR_BOUNDARY_TAIL_SPACE_MISMATCH,
            QA::ERR_SPACE_MISMATCH_AFTER_TAG,
            QA::ERR_SPACE_MISMATCH_BEFORE_TAG,
            QA::ERR_SYMBOL_MISMATCH,
            QA::ERR_NEWLINE_MISMATCH => self::MISMATCH_CATEGORY,
            QA::ERR_ICU_VALIDATION => self::ICU_CATEGORY,
            default => self::TAGS_CATEGORY,
        };

        if (!isset($this->structure[$error_type]['Categories'][$category])) {
            $this->structure[$error_type]['Categories'][$category] = [];
        }

        if (!in_array($content, $this->structure[$error_type]['Categories'][$category], true)) {
            $this->structure[$error_type]['Categories'][$category][] = $content;
        }
    }
}