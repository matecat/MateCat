<?php

namespace Utils\AIAssistant;

interface AIClientInterface
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

    /**
     * @return bool|array<string, mixed>
     */
    public function evaluateTranslation(
        string $sourceLanguage,
        string $targetLanguage,
        string $text,
        string $translation,
        string $style
    ): bool|array;

    public function findContextForAWord(string $word, string $phrase, string $target, callable $callback): void;
}
