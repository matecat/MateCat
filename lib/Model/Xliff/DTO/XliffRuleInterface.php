<?php

namespace Model\Xliff\DTO;

use Exception;

interface XliffRuleInterface
{

    /**
     * @param string|null $type
     *
     * @return string[]
     * @throws Exception
     */
    public function getStates(?string $type = null): array;

    /**
     * @return string
     * @throws Exception
     */
    public function asEditorStatus(): string;

    /**
     * @param string $source
     * @param string $target
     *
     * @return bool
     */
    public function isTranslated(string $source, string $target): bool;

    /**
     * @return string
     * @throws Exception
     */
    public function asMatchType(): string;

    /**
     * @param int   $raw_word_count
     * @param array $payable_rates
     *
     * @return float
     * @throws Exception
     */
    public function asStandardWordCount(int $raw_word_count, array $payable_rates): float;

    /**
     * @param int   $raw_word_count
     * @param array $payable_rates
     *
     * @return float
     * @throws Exception
     */
    public function asEquivalentWordCount(int $raw_word_count, array $payable_rates): float;

}