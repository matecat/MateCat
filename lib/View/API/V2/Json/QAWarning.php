<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 04/09/2018
 * Time: 12:40
 */

namespace View\API\V2\Json;

use Utils\LQA\QA;

class QAWarning {

    protected array $structure;

    const string GLOSSARY_CATEGORY = "GLOSSARY";
    const string TAGS_CATEGORY     = "TAGS";
    const string SIZE_CATEGORY     = "SIZE";
    const string MISMATCH_CATEGORY = "MISMATCH";

    protected function pushErrorSegment( $error_type, $error_category, $content ) {

        switch ( $error_category ) {

            case QA::ERR_SIZE_RESTRICTION:
                $category = self::SIZE_CATEGORY;
                break;

            case QA::ERR_SPACE_MISMATCH_TEXT:
            case QA::ERR_TAB_MISMATCH:
            case QA::ERR_SPACE_MISMATCH:
            case QA::ERR_BOUNDARY_HEAD_SPACE_MISMATCH:
            case QA::ERR_BOUNDARY_TAIL_SPACE_MISMATCH:
            case QA::ERR_SPACE_MISMATCH_AFTER_TAG:
            case QA::ERR_SPACE_MISMATCH_BEFORE_TAG:
            case QA::ERR_SYMBOL_MISMATCH:
            case QA::ERR_NEWLINE_MISMATCH:
                $category = self::MISMATCH_CATEGORY;
                break;
            case QA::ERR_UNCLOSED_G_TAG:
            case QA::ERR_TAG_ORDER:
            case QA::ERR_UNCLOSED_X_TAG:
            case QA::ERR_TAG_ID:
            case QA::ERR_TAG_MISMATCH:
            default:
                $category = self::TAGS_CATEGORY;
                break;
        }

        if ( !isset( $this->structure[ $error_type ][ 'Categories' ][ $category ] ) ) {
            $this->structure[ $error_type ][ 'Categories' ][ $category ] = [];
        }

        if ( !in_array( $content, $this->structure[ $error_type ][ 'Categories' ][ $category ] ) ) {
            $this->structure[ $error_type ][ 'Categories' ][ $category ][] = $content;
        }

    }
}