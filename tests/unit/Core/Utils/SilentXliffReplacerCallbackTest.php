<?php

namespace Matecat\Core\Utils;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
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
