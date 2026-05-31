<?php

namespace unit\Utils;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Utils\XliffReplacer\SilentXliffReplacerCallback;

class SilentXliffReplacerCallbackTest extends TestCase
{
    #[Test]
    public function thereAreErrorsAlwaysReturnsFalse(): void
    {
        $callback = new SilentXliffReplacerCallback();

        $this->assertFalse($callback->thereAreErrors(1, 'source', 'translation'));
        $this->assertFalse($callback->thereAreErrors(1, 'source', 'translation', ['ref' => 'val'], 'error'));
    }
}
