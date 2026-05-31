<?php

namespace Utils\AIAssistant;

interface AlternativeTranslationsClientInterface
{
    /**
     * @return list<mixed>
     */
    public function manageAlternativeTranslations(
        string $sourceLanguage,
        string $targetLanguage,
        string $sourceSentence,
        string $sourceContextSentencesString,
        string $targetSentence,
        string $targetContextSentencesString,
        string $excerpt,
        string $styleInstructions
    ): array;
}
