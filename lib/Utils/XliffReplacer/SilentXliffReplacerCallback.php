<?php

namespace Utils\XliffReplacer;

use Exception;
use Matecat\XliffParser\XliffReplacer\XliffReplacerCallbackInterface;

class SilentXliffReplacerCallback implements XliffReplacerCallbackInterface {

    /**
     * Error checking is disabled
     *
     * @inheritDoc
     * @throws Exception
     */
    public function thereAreErrors( int $segmentId, string $segment, string $translation, ?array $dataRefMap = [], ?string $error = null ): bool {
        return false;
    }
}