<?php

namespace Matecat\Core\Controllers\Abstracts\Authentication;

use Controller\Abstracts\Authentication\CookieManager;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\Constants;

#[Group('unit')]
class CookieManagerTest extends AbstractTest
{
    /**
     * A CookieManager whose low-level write is intercepted so the emitted
     * name/value/options can be asserted without touching PHP's setcookie().
     */
    private function spyingCookieManager(): CookieManager
    {
        return new class extends CookieManager {
            /** @var list<array{name:string,value:string,options:array<string,mixed>}> */
            public array $writes = [];

            protected function writeCookie(string $name, string $value, array $options): bool
            {
                $this->writes[] = ['name' => $name, 'value' => $value, 'options' => $options];

                return true;
            }
        };
    }

    #[Test]
    public function setAppliesSecureStrictHttpOnlyDefaults(): void
    {
        $cm = $this->spyingCookieManager();
        $cm->set('c', 'v', expires: 123);

        $write = $cm->writes[0];
        self::assertSame('c', $write['name']);
        self::assertSame('v', $write['value']);
        self::assertSame(123, $write['options']['expires']);
        self::assertTrue($write['options']['secure']);
        self::assertTrue($write['options']['httponly']);
        self::assertSame('Strict', $write['options']['samesite']);
        self::assertSame('/', $write['options']['path']);
        self::assertArrayHasKey('domain', $write['options']);
    }

    #[Test]
    public function setHonoursEveryOverride(): void
    {
        $cm = $this->spyingCookieManager();
        $cm->set('c', 'v', expires: 5, secure: false, httpOnly: false, sameSite: 'None', path: '/x', domain: 'example.com');

        $o = $cm->writes[0]['options'];
        self::assertSame(5, $o['expires']);
        self::assertFalse($o['secure']);
        self::assertFalse($o['httponly']);
        self::assertSame('None', $o['samesite']);
        self::assertSame('/x', $o['path']);
        self::assertSame('example.com', $o['domain']);
    }

    #[Test]
    public function deleteUnsetsTheCookieAndWritesAPastExpiry(): void
    {
        $_COOKIE['gone'] = 'x';

        $cm     = $this->spyingCookieManager();
        $result = $cm->delete('gone');

        self::assertTrue($result);
        self::assertArrayNotHasKey('gone', $_COOKIE);
        self::assertSame('gone', $cm->writes[0]['name']);
        self::assertSame('', $cm->writes[0]['value']);
        self::assertLessThan(time(), $cm->writes[0]['options']['expires']);
    }

    #[Test]
    public function setEmptyCookieValueIfMissingWritesEmptyValueWhenCookieAbsent(): void
    {
        unset($_COOKIE['prefCookie']);

        $cm     = $this->spyingCookieManager();
        $result = $cm->setEmptyCookieValueIfMissing('prefCookie');

        self::assertTrue($result);
        self::assertCount(1, $cm->writes);
        self::assertSame('prefCookie', $cm->writes[0]['name']);
        self::assertSame(Constants::EMPTY_VAL, $cm->writes[0]['value']);
        self::assertSame('Strict', $cm->writes[0]['options']['samesite']);
    }

    #[Test]
    public function setEmptyCookieValueIfMissingDoesNothingWhenCookieAlreadyPresent(): void
    {
        $_COOKIE['prefCookie'] = 'already-set';

        $cm     = $this->spyingCookieManager();
        $result = $cm->setEmptyCookieValueIfMissing('prefCookie');

        self::assertFalse($result);
        self::assertCount(0, $cm->writes);

        unset($_COOKIE['prefCookie']);
    }
}
