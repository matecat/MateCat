<?php

namespace FiltersXliffConfig\Xliff\DTO;

interface XliffRuleInterface {

    /**
     * @param $type
     *
     * @return string[]
     */
    public function getStates( $type = null ): array;

    /**
     * @return string
     */
    public function asEditorStatus(): string;

    /**
     * @param string|null $source
     * @param string|null $target
     *
     * @return bool
     */
    public function isTranslated( string $source = null, string $target = null ): bool;

    /**
     * @return string
     */
    public function asMatchType(): string;

    /**
     * @param int   $raw_word_count
     * @param array $payable_rates
     *
     * @return float
     */
    public function asStandardWordCount( int $raw_word_count, array $payable_rates ): float;

    /**
     * @param int   $raw_word_count
     * @param array $payable_rates
     *
     * @return float
     */
    public function asEquivalentWordCount( int $raw_word_count, array $payable_rates ): float;

}