<?php

namespace Model\Xliff\DTO;

use Constants\XliffTranslationStatus;
use Constants_TranslationStatus;
use LogicException;

class DefaultRule extends AbstractXliffRule {

    /**
     * @see https://docs.oasis-open.org/xliff/v1.2/os/xliff-core.html
     * @see https://docs.oasis-open.org/xliff/xliff-core/v2.0/xliff-core-v2.0.html
     */
    protected static array $_STATES           = XliffTranslationStatus::ALL_STATES;
    protected static array $_STATE_QUALIFIERS = XliffTranslationStatus::STATE_QUALIFIER_12;

    const ALLOWED_EDITOR_VALUES = [ null ];

    /**
     * @param $analysis
     */
    protected function setAnalysis( $analysis ): void {
        if ( $analysis == AbstractXliffRule::_ANALYSIS_NEW ) {
            throw new LogicException( "DefaultRule is designed to be pre-translated only.", 500 );
        }
        parent::setAnalysis( $analysis );
    }

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
     * @param string $source
     * @param string $target
     *
     * @return bool
     */
    public function isTranslated( string $source, string $target ): bool {

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