<?php

namespace Utils\AIAssistant;

interface ContextExplainerClientInterface
{
    public function findContextForAWord(string $word, string $phrase, string $target, callable $callback): void;
}
