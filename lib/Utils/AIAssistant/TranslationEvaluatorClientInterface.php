<?php

namespace Utils\AIAssistant;

interface TranslationEvaluatorClientInterface
{
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
}
