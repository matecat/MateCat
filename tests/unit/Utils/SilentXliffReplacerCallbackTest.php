<?php

namespace unit\Utils;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\XliffReplacer\SilentXliffReplacerCallback;

class SilentXliffReplacerCallbackTest extends AbstractTest
{
    #[Test]
    public function thereAreErrorsAlwaysReturnsFalse(): void
    {
        $callback = new SilentXliffReplacerCallback();

        $this->assertFalse($callback->thereAreErrors(1, 'source', 'translation'));
        $this->assertFalse($callback->thereAreErrors(1, 'source', 'translation', ['ref' => 'val'], 'error'));
    }
}
