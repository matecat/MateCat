<?php

namespace Utils\XliffReplacer;

use Matecat\XliffParser\XliffReplacer\XliffReplacerCallbackInterface;

class SilentXliffReplacerCallback implements XliffReplacerCallbackInterface
{

    /**
     * Error checking is disabled
     *
     * @inheritDoc
     */
    public function thereAreErrors(int $segmentId, string $segment, string $translation, ?array $dataRefMap = [], ?string $error = null): bool
    {
        return false;
    }
}