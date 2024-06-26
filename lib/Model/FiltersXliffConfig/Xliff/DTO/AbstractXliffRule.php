<?php

namespace FiltersXliffConfig\Xliff\DTO;

use API\App\Json\Analysis\MatchConstants;
use Constants_TranslationStatus;
use DomainException;
use JsonSerializable;
use RecursiveArrayObject;

abstract class AbstractXliffRule implements XliffRuleInterface, JsonSerializable {
    protected static $_STATE_QUALIFIERS = [];
    protected static $_STATES           = [];

    // analysis behaviour
    const _ANALYSIS_PRE_TRANSLATED = "pre-translated";
    const _ANALYSIS_NEW            = "new";

    // editor states
    const _TRANSLATED            = "translated";
    const _APPROVED              = "approved";
    const _APPROVED2             = "approved2";
    const _IGNORE_TARGET_CONTENT = "ignore-target-content";
    const _KEEP_TARGET_CONTENT   = "keep-target-content";

    const ALLOWED_ANALYSIS_VALUES = [
            self::_ANALYSIS_PRE_TRANSLATED,
            self::_ANALYSIS_NEW
    ];

    const ALLOWED_EDITOR_VALUES = [
            self::_TRANSLATED,
            self::_APPROVED,
            self::_APPROVED2,
            self::_IGNORE_TARGET_CONTENT,
            self::_KEEP_TARGET_CONTENT,
    ];

    const ALLOWED_MATCH_TYPES = MatchConstants::forValue;

    /**
     * @var string[]
     */
    protected $states = [
            'states'           => [],
            'state-qualifiers' => []
    ];

    protected static $VALIDATION_MAP = [
            self::_ANALYSIS_NEW            => [
                    self::_IGNORE_TARGET_CONTENT,
                    self::_KEEP_TARGET_CONTENT
            ],
            self::_ANALYSIS_PRE_TRANSLATED => [
                    self::_TRANSLATED,
                    self::_APPROVED,
                    self::_APPROVED2,
            ],
    ];

    /**
     * @var string
     */
    protected $analysis;
    /**
     * @var string
     */
    protected $editor;
    /**
     * @var string
     */
    protected $matchCategory = 'ice';

    /**
     * AbstractXliffRule constructor.
     *
     * @param array       $states
     * @param string      $analysis
     * @param string|null $editor
     * @param string|null $matchCategory
     */
    public function __construct( array $states, string $analysis, string $editor = null, string $matchCategory = null ) {
        $this->setStates( $states );
        $this->setAnalysis( $analysis );
        $this->setEditor( $editor );
        $this->setMatchCategory( $matchCategory );
        $this->validateAnalysisAndEditor( $analysis, $editor );
    }

    /**
     * @param $analysis
     * @param $editor
     */
    protected function validateAnalysisAndEditor( $analysis, $editor ): void {

        if ( !isset( static::$VALIDATION_MAP[ $analysis ] ) ) {
            throw new DomainException( "Wrong analysis value", 400 );
        }

        if ( !in_array( $editor, static::$VALIDATION_MAP[ $analysis ] ) ) {
            throw new DomainException( "Wrong analysis/editor combination", 400 );
        }
    }

    /**
     * @param array $states
     */
    protected function setStates( array $states ): void {

        foreach ( $states as $state ) {
            if ( !in_array( $state, array_merge( static::$_STATES, static::$_STATE_QUALIFIERS ) ) ) {
                throw new DomainException( "Wrong state value", 400 );
            }

            if ( in_array( $state, static::$_STATES ) ) {
                $this->states[ 'states' ][] = strtolower( $state );
                continue;
            }

            if ( in_array( $state, static::$_STATE_QUALIFIERS ) ) {
                $this->states[ 'state-qualifiers' ][] = strtolower( $state );
            }

        }

    }

    /**
     * @param $analysis
     */
    protected function setAnalysis( $analysis ): void {
        if ( !in_array( $analysis, static::ALLOWED_ANALYSIS_VALUES ) ) {
            throw new DomainException( "Wrong analysis value", 400 );
        }

        $this->analysis = strtolower( $analysis );
    }

    /**
     * @param $editor
     */
    protected function setEditor( $editor ): void {
        if ( !in_array( $editor, static::ALLOWED_EDITOR_VALUES ) ) {
            throw new DomainException( "Wrong editor value", 400 );
        }

        $this->editor = strtolower( $editor );
    }

    /**
     * Accept null values and keep the default ICE
     *
     * @param string|null $matchCategory
     *
     * @return void
     */
    protected function setMatchCategory( string $matchCategory = null ): void {

        if ( !empty( $matchCategory ) && !in_array( $matchCategory, static::ALLOWED_MATCH_TYPES ) ) {
            throw new DomainException( "Wrong match_category value", 400 );
        } elseif ( !empty( $matchCategory ) ) {
            $this->matchCategory = $matchCategory;
        }

    }

    /**
     * @param RecursiveArrayObject $structure
     *
     * @return AbstractXliffRule
     */
    public static function fromArrayObject( RecursiveArrayobject $structure ): AbstractXliffRule {
        return new static( $structure[ 'states' ]->getArrayCopy(), $structure[ 'analysis' ], $structure[ 'editor' ], $structure[ 'match_category' ] ?? null );
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array {

        return [
                'states'         => array_merge( $this->states[ 'states' ], $this->states[ 'state-qualifiers' ] ),
                'analysis'       => $this->analysis,
                'editor'         => $this->editor,
                'match_category' => $this->matchCategory
        ];
    }

    /**
     * @param string|null $type
     *
     * @return string[]
     */
    public function getStates( $type = null ): array {
        switch ( $type ) {
            case 'states':
                return $this->states[ 'states' ];
            case 'state-qualifiers':
                return $this->states[ 'state-qualifiers' ];
            default:
                return array_merge( $this->states[ 'states' ], $this->states[ 'state-qualifiers' ] );
        }
    }

    /**
     * @return string
     */
    protected function getAnalysis(): string {
        return $this->analysis;
    }

    /**
     * @return string
     */
    protected function getEditor(): string {
        return $this->editor;
    }

    /**
     * @return string
     */
    public function asEditorStatus(): string {

        if ( $this->getAnalysis() == AbstractXliffRule::_ANALYSIS_PRE_TRANSLATED ) {
            switch ( $this->getEditor() ) {
                case AbstractXliffRule::_TRANSLATED:
                    return Constants_TranslationStatus::STATUS_TRANSLATED;
                case AbstractXliffRule::_APPROVED:
                    return Constants_TranslationStatus::STATUS_APPROVED;
                case AbstractXliffRule::_APPROVED2:
                    return Constants_TranslationStatus::STATUS_APPROVED2;
            }
        }

        // here is impossible to have an editor rule AbstractXliffRule::_IGNORE_TARGET_CONTENT, so keep as draft
        return Constants_TranslationStatus::STATUS_DRAFT;

    }

    /**
     * @param string|null $source
     * @param string|null $target
     *
     * @return bool
     */
    public function isTranslated( string $source = null, string $target = null ): bool {
        if ( $this->getAnalysis() == AbstractXliffRule::_ANALYSIS_NEW && $this->getEditor() == AbstractXliffRule::_IGNORE_TARGET_CONTENT ) {
            return false;
        } else {
            // all cases
            return true;
        }
    }

    /**
     * @return string
     */
    public function asMatchType(): string {
        return MatchConstants::toInternalMatchTypeValue( $this->matchCategory );
    }

    /**
     * @param int   $raw_word_count
     * @param array $payable_rates
     *
     * @return float
     */
    public function asStandardWordCount( int $raw_word_count, array $payable_rates ): float {
        if ( $this->matchCategory == MatchConstants::_MT ) {
            return $raw_word_count;
        }

        return $this->asEquivalentWordCount( $raw_word_count, $payable_rates );
    }

    /**
     * @param int   $raw_word_count
     * @param array $payable_rates
     *
     * @return float
     */
    public function asEquivalentWordCount( int $raw_word_count, array $payable_rates ): float {
        return floatval( $raw_word_count / 100 * ( $payable_rates[ $this->asMatchType() ] ?? 0 ) );
    }

}