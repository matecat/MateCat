<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 11/06/24
 * Time: 17:27
 *
 */

namespace FiltersXliffConfig\Xliff\DTO;

use Constants\XliffTranslationStatus;
use Constants_TranslationStatus;

class DefaultRule extends AbstractXliffRule {

    /**
     * @see https://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html
     */
    protected static $_STATES           = XliffTranslationStatus::ALL_STATES;
    protected static $_STATE_QUALIFIERS = XliffTranslationStatus::STATE_QUALIFIER_12;

    const ALLOWED_EDITOR_VALUES = [ null ];

    protected static $VALIDATION_MAP = [
            self::_ANALYSIS_PRE_TRANSLATED => [ null ],
    ];

    public function asEditorStatus(): string {

        // default behavior
        if ( !empty( $this->getStates( "state-qualifiers" )[ 0 ] ) ) {
            if ( XliffTranslationStatus::isStateQualifierNew( $this->getStates( "state-qualifiers" )[ 0 ] ) ) {
                return Constants_TranslationStatus::STATUS_NEW;
            }
        }

        // default behavior
        if ( !empty( $this->getStates( "states" )[ 0 ] ) ) {

            $state = $this->getStates( "states" )[ 0 ];

            if ( XliffTranslationStatus::isStatusNew( $state ) ) {
                return Constants_TranslationStatus::STATUS_NEW;
            }

            if ( XliffTranslationStatus::isTranslated( $state ) ) {
                return Constants_TranslationStatus::STATUS_TRANSLATED;
            }

            if ( XliffTranslationStatus::isRevision( $state ) ) {
                return Constants_TranslationStatus::STATUS_APPROVED;
            }

            if ( XliffTranslationStatus::isFinalState( $state ) ) {
                return Constants_TranslationStatus::STATUS_APPROVED2;
            }

        }

        // retro-compatibility
        return Constants_TranslationStatus::STATUS_APPROVED;

    }

    /**
     * @param string|null $source
     * @param string|null $target
     *
     * @return bool
     */
    public function isTranslated( string $source = null, string $target = null ): bool {

        if ( !empty( $this->getStates( "state-qualifiers" )[ 0 ] ) ) { // default behavior
            // Ignore translations for fuzzy matches (xliff 1.2)
            // fuzzy-match, mt-suggestion, leveraged-tm, leveraged-inherited, leveraged-mt
            // set those state-qualifiers as NEW
            return !XliffTranslationStatus::isStateQualifierNew( strtolower( $this->getStates( "state-qualifiers" )[ 0 ] ) );
        }

        if ( !empty( $this->getStates( "states" )[ 0 ] ) ) { // default behaviour
            return !XliffTranslationStatus::isStatusNew( strtolower( $this->getStates( "states" )[ 0 ] ) );
        }

        if ( $source != $target ) {

            // evaluate if a different source and target should be considered translated
            return !empty( $target );

        }

        return false;

    }

}