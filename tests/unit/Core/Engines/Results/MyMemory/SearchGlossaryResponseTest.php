<?php

declare(strict_types=1);

namespace Matecat\Core\Engines\Results\MyMemory;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Engines\Results\MyMemory\SearchGlossaryResponse;

#[Group('unit')]
class SearchGlossaryResponseTest extends AbstractTest
{
    #[Test]
    public function constructor_throws_when_response_is_not_an_array(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid response');
        $this->expectExceptionCode(-1);

        new SearchGlossaryResponse('not an array');
    }

    #[Test]
    public function constructor_throws_when_response_is_null(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid response');

        new SearchGlossaryResponse(null);
    }

    #[Test]
    public function constructor_defaults_matches_to_empty_array_when_key_is_absent(): void
    {
        $response = new SearchGlossaryResponse(['responseStatus' => 200]);

        $this->assertSame([], $response->matches);
    }

    #[Test]
    public function constructor_stores_the_matches_key(): void
    {
        $matches = [
            ['id' => '1', 'segment' => 'invoice', 'translation' => 'fattura'],
        ];

        $response = new SearchGlossaryResponse(['matches' => $matches]);

        $this->assertSame($matches, $response->matches);
    }
}
