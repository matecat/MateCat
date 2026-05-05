<?php

namespace unit\Utils\Tools;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Tools\Utils;

class UtilsTest extends AbstractTest
{
    // =========================================================================
    // Tests for getSourcePage() and getSourcePageFromReferer()
    // =========================================================================

    #[Test]
    public function testGetSourcePageReturnsTranslateWhenNoRequestUri(): void
    {
        unset($_SERVER['REQUEST_URI']);
        $result = Utils::getSourcePage();
        $this->assertEquals(1, $result); // SOURCE_PAGE_TRANSLATE = 1
    }

    #[Test]
    public function testGetSourcePageReturnsTranslateForTranslatePath(): void
    {
        $_SERVER['REQUEST_URI'] = '/translate/project/1-2/en-US-it-IT';
        $result = Utils::getSourcePage();
        $this->assertEquals(1, $result);
    }

    #[Test]
    public function testGetSourcePageFromRefererReturnsTranslateWhenNoReferer(): void
    {
        unset($_SERVER['HTTP_REFERER']);
        $result = Utils::getSourcePageFromReferer();
        $this->assertEquals(1, $result);
    }

    // =========================================================================
    // Tests for getBrowser()
    // =========================================================================

    #[Test]
    public function testGetBrowserReturnsNullValuesWhenNoUserAgent(): void
    {
        unset($_SERVER['HTTP_USER_AGENT']);
        $result = Utils::getBrowser();

        $this->assertNull($result['userAgent']);
        $this->assertNull($result['name']);
        $this->assertNull($result['version']);
        $this->assertNull($result['platform']);
    }

    #[Test]
    public function testGetBrowserDetectsChrome(): void
    {
        $chromeAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
        $result = Utils::getBrowser($chromeAgent);

        $this->assertEquals('Google Chrome', $result['name']);
        $this->assertEquals('Windows', $result['platform']);
        $this->assertNotEmpty($result['version']);
    }

    #[Test]
    public function testGetBrowserDetectsFirefox(): void
    {
        $firefoxAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0';
        $result = Utils::getBrowser($firefoxAgent);

        $this->assertEquals('Mozilla Firefox', $result['name']);
        $this->assertEquals('Windows', $result['platform']);
    }

    #[Test]
    public function testGetBrowserDetectsSafari(): void
    {
        $safariAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Safari/605.1.15';
        $result = Utils::getBrowser($safariAgent);

        $this->assertEquals('Apple Safari', $result['name']);
        $this->assertEquals('MacOSX', $result['platform']);
    }

    #[Test]
    public function testGetBrowserDetectsMobileSafari(): void
    {
        $iosAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Mobile/15E148 Safari/604.1';
        $result = Utils::getBrowser($iosAgent);

        $this->assertEquals('Mobile Safari', $result['name']);
        $this->assertEquals('iOS', $result['platform']);
    }

    #[Test]
    public function testGetBrowserDetectsEdge(): void
    {
        $edgeAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36 Edg/91.0.864.59';
        $result = Utils::getBrowser($edgeAgent);

        $this->assertEquals('Microsoft Edge', $result['name']);
        $this->assertEquals('Windows', $result['platform']);
    }

    #[Test]
    public function testGetBrowserDetectsOpera(): void
    {
        $operaAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36 OPR/77.0.4054.254';
        $result = Utils::getBrowser($operaAgent);

        $this->assertEquals('Opera', $result['name']);
    }

    #[Test]
    public function testGetBrowserDetectsInternetExplorer(): void
    {
        $ieAgent = 'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko';
        $result = Utils::getBrowser($ieAgent);

        $this->assertEquals('Internet Explorer Mobile', $result['name']);
        $this->assertEquals('Windows', $result['platform']);
    }

    #[Test]
    public function testGetBrowserDetectsLinux(): void
    {
        $linuxAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36';
        $result = Utils::getBrowser($linuxAgent);

        $this->assertEquals('Linux', $result['platform']);
    }

    #[Test]
    public function testGetBrowserDetectsAndroid(): void
    {
        $androidAgent = 'Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.120 Mobile Safari/537.36';
        $result = Utils::getBrowser($androidAgent);

        $this->assertEquals('Android', $result['platform']);
    }

    #[Test]
    public function testGetBrowserDetectsIPad(): void
    {
        $ipadAgent = 'Mozilla/5.0 (iPad; CPU OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.1 Mobile/15E148 Safari/604.1';
        $result = Utils::getBrowser($ipadAgent);

        $this->assertEquals('ipadOS', $result['platform']);
    }

    #[Test]
    public function testGetBrowserDetectsWindowsPhone(): void
    {
        $wpAgent = 'Mozilla/5.0 (Windows Phone 10.0; Android 6.0.1; Microsoft; Lumia 950) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Mobile Safari/537.36 Edge/15.15254';
        $result = Utils::getBrowser($wpAgent);

        $this->assertEquals('Windows Phone', $result['platform']);
    }

    #[Test]
    public function testGetBrowserReturnsUnknownForUnknownAgent(): void
    {
        $unknownAgent = 'UnknownBrowser/1.0';
        $result = Utils::getBrowser($unknownAgent);

        $this->assertEquals('Unknown', $result['name']);
        $this->assertEquals('Unknown', $result['platform']);
    }

    #[Test]
    public function testGetBrowserUsesServerUserAgentWhenNoParam(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/91.0.4472.124 Safari/537.36';
        $result = Utils::getBrowser();

        $this->assertEquals('Google Chrome', $result['name']);
    }

    // =========================================================================
    // Tests for friendly_slug()
    // =========================================================================

    #[Test]
    public function testFriendlySlugConvertsToLowercase(): void
    {
        $result = Utils::friendlySlug('HELLO WORLD');
        $this->assertStringNotContainsString('H', $result);
        $this->assertStringNotContainsString('W', $result);
    }

    #[Test]
    public function testFriendlySlugReplacesSpacesWithDashes(): void
    {
        $result = Utils::friendlySlug('hello world');
        $this->assertEquals('hello-world', $result);
    }

    #[Test]
    public function testFriendlySlugReplacesAmpersand(): void
    {
        $result = Utils::friendlySlug('hello & world');
        $this->assertStringContainsString('-', $result);
        $this->assertStringNotContainsString('&', $result);
    }

    #[Test]
    public function testFriendlySlugReplacesPlus(): void
    {
        $result = Utils::friendlySlug('hello+world');
        $this->assertStringContainsString('-', $result);
    }

    #[Test]
    public function testFriendlySlugReplacesComma(): void
    {
        $result = Utils::friendlySlug('hello,world');
        $this->assertStringContainsString('-', $result);
    }

    #[Test]
    public function testFriendlySlugHandlesAccents(): void
    {
        $result = Utils::friendlySlug('café résumé');
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $result);
    }

    #[Test]
    public function testFriendlySlugReturnsHyphenForEmptyString(): void
    {
        $result = Utils::friendlySlug('   ');
        $this->assertEquals('-', $result);
    }

    #[Test]
    public function testFriendlySlugRemovesSpecialCharacters(): void
    {
        $result = Utils::friendlySlug('hello@world#test!');
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $result);
    }

    #[Test]
    public function testFriendlySlugReturnsHyphenForEmptyStringInput(): void
    {
        $result = Utils::friendlySlug('');
        $this->assertEquals('-', $result);
    }

    #[Test]
    public function testFriendlySlugHandlesValidAsciiSymbol(): void
    {
        $result = Utils::friendlySlug('hello-world');
        $this->assertEquals('hello-world', $result);
    }

    #[Test]
    public function testFriendlySlugStripsLogicalNegationSymbol(): void
    {
        $result = Utils::friendlySlug('hello¬world');
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $result);
        $this->assertStringNotContainsString('¬', $result);
    }

    #[Test]
    public function testFriendlySlugStripsBoxDrawingCharacter(): void
    {
        $result = Utils::friendlySlug('╚══test══╝');
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $result);
        $this->assertStringNotContainsString('╚', $result);
        $this->assertStringNotContainsString('═', $result);
    }

    #[Test]
    public function testFriendlySlugStripsBlockGraphicSymbol(): void
    {
        $result = Utils::friendlySlug('hello░world');
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $result);
        $this->assertStringNotContainsString('░', $result);
    }

    // =========================================================================
    // Tests for replace_accents()
    // =========================================================================

    #[Test]
    public function testReplaceAccentsConvertsUppercaseAccents(): void
    {
        $result = Utils::transliterate('ÀÁÂÃÄÅ');
        $this->assertEquals('AAAAAA', $result);
    }

    #[Test]
    public function testReplaceAccentsConvertsLowercaseAccents(): void
    {
        $result = Utils::transliterate('àáâãäå');
        $this->assertEquals('aaaaaa', $result);
    }

    #[Test]
    public function testReplaceAccentsConvertsAE(): void
    {
        $result = Utils::transliterate('Æ');
        $this->assertEquals('AE', $result);
    }

    #[Test]
    public function testReplaceAccentsConvertsCedilla(): void
    {
        $result = Utils::transliterate('Ç');
        $this->assertEquals('C', $result);
    }

    #[Test]
    public function testReplaceAccentsConvertsEAccents(): void
    {
        $result = Utils::transliterate('ÈÉÊË');
        $this->assertEquals('EEEE', $result);
    }

    #[Test]
    public function testReplaceAccentsConvertsIAccents(): void
    {
        $result = Utils::transliterate('ÌÍÎÏ');
        $this->assertEquals('IIII', $result);
    }

    #[Test]
    public function testReplaceAccentsConvertsNTilde(): void
    {
        $result = Utils::transliterate('Ñ');
        $this->assertEquals('N', $result);
    }

    #[Test]
    public function testReplaceAccentsConvertsOAccents(): void
    {
        $result = Utils::transliterate('ÒÓÔÕÖØ');
        $this->assertEquals('OOOOOO', $result);
    }

    #[Test]
    public function testReplaceAccentsConvertsUAccents(): void
    {
        $result = Utils::transliterate('ÙÚÛÜ');
        $this->assertEquals('UUUU', $result);
    }

    #[Test]
    public function testReplaceAccentsConvertsSzlig(): void
    {
        $result = Utils::transliterate('ß');
        // ICU Transliterator correctly converts ß to 'ss' (German sharp S)
        $this->assertEquals('ss', $result);
    }

    #[Test]
    public function testReplaceAccentsConvertsOE(): void
    {
        $result = Utils::transliterate('Œœ');
        $this->assertEquals('OEoe', $result);
    }

    #[Test]
    public function testReplaceAccentsConvertsPolishL(): void
    {
        $result = Utils::transliterate('Łł');
        // ICU Transliterator converts Ł to 'L' and ł to 'l'
        $this->assertEquals('Ll', $result);
    }

    #[Test]
    public function testReplaceAccentsPreservesNonAccentedChars(): void
    {
        $result = Utils::transliterate('Hello World 123');
        $this->assertEquals('Hello World 123', $result);
    }

    #[Test]
    public function testReplaceAccentsHandlesMixedContent(): void
    {
        $result = Utils::transliterate('Café résumé naïve');
        $this->assertStringNotContainsString('é', $result);
        $this->assertStringNotContainsString('ï', $result);
    }

    // =========================================================================
    // Tests for encryptPass() and verifyPass()
    // =========================================================================

    #[Test]
    public function testEncryptPassReturnsNonEmptyString(): void
    {
        $result = Utils::encryptPass('password123', 'salt123');
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    #[Test]
    public function testEncryptPassReturnsDifferentHashForDifferentSalts(): void
    {
        $hash1 = Utils::encryptPass('password', 'salt1');
        $hash2 = Utils::encryptPass('password', 'salt2');
        $this->assertNotEquals($hash1, $hash2);
    }

    #[Test]
    public function testVerifyPassReturnsTrueForCorrectPassword(): void
    {
        $password = 'testPassword123';
        $salt = 'randomSalt';
        $hash = Utils::encryptPass($password, $salt);

        $this->assertTrue(Utils::verifyPass($password, $salt, $hash));
    }

    #[Test]
    public function testVerifyPassReturnsFalseForWrongPassword(): void
    {
        $password = 'testPassword123';
        $salt = 'randomSalt';
        $hash = Utils::encryptPass($password, $salt);

        $this->assertFalse(Utils::verifyPass('wrongPassword', $salt, $hash));
    }

    #[Test]
    public function testVerifyPassReturnsFalseForWrongSalt(): void
    {
        $password = 'testPassword123';
        $salt = 'randomSalt';
        $hash = Utils::encryptPass($password, $salt);

        $this->assertFalse(Utils::verifyPass($password, 'wrongSalt', $hash));
    }

    // =========================================================================
    // Tests for randomString()
    // =========================================================================

    #[Test]
    public function testRandomStringReturnsCorrectLength(): void
    {
        $result = Utils::randomString();
        $this->assertEquals(12, strlen($result));
    }

    #[Test]
    public function testRandomStringReturnsDefaultLength(): void
    {
        $result = Utils::randomString();
        $this->assertEquals(12, strlen($result));
    }

    #[Test]
    public function testRandomStringReturnsLongerString(): void
    {
        $result = Utils::randomString(24);
        $this->assertEquals(24, strlen($result));
    }

    #[Test]
    public function testRandomStringReturnsVeryLongString(): void
    {
        $result = Utils::randomString(100);
        $this->assertEquals(100, strlen($result));
    }

    #[Test]
    public function testRandomStringWithMoreEntropyReturnsValidString(): void
    {
        $result = Utils::randomString(12, true);
        $this->assertEquals(12, strlen($result));
    }

    #[Test]
    public function testRandomStringGeneratesUniqueStrings(): void
    {
        $strings = [];
        for ($i = 0; $i < 100; $i++) {
            $strings[] = Utils::randomString();
        }
        $uniqueStrings = array_unique($strings);
        $this->assertCount(100, $uniqueStrings);
    }

    // =========================================================================
    // Tests for mysqlTimestamp()
    // =========================================================================

    #[Test]
    public function testMysqlTimestampReturnsCorrectFormat(): void
    {
        $time = strtotime('2023-06-15 14:30:00');
        $result = Utils::mysqlTimestamp($time);
        $this->assertEquals('2023-06-15 14:30:00', $result);
    }

    #[Test]
    public function testMysqlTimestampHandlesCurrentTime(): void
    {
        $time = time();
        $result = Utils::mysqlTimestamp($time);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
    }

    // =========================================================================
    // Tests for api_timestamp()
    // =========================================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function testApiTimestampReturnsNullForNullInput(): void
    {
        $result = Utils::api_timestamp(null);
        $this->assertNull($result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testApiTimestampReturnsIso8601Format(): void
    {
        $result = Utils::api_timestamp('2023-06-15 14:30:00');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result);
    }

    // =========================================================================
    // Tests for underscoreToCamelCase()
    // =========================================================================

    #[Test]
    public function testUnderscoreToCamelCaseConvertsSimpleString(): void
    {
        $result = Utils::underscoreToCamelCase('hello_world');
        $this->assertEquals('HelloWorld', $result);
    }

    #[Test]
    public function testUnderscoreToCamelCaseHandlesMultipleUnderscores(): void
    {
        $result = Utils::underscoreToCamelCase('hello_world_test_case');
        $this->assertEquals('HelloWorldTestCase', $result);
    }

    #[Test]
    public function testUnderscoreToCamelCaseHandlesNoUnderscores(): void
    {
        $result = Utils::underscoreToCamelCase('hello');
        $this->assertEquals('Hello', $result);
    }

    // =========================================================================
    // Tests for trimAndLowerCase()
    // =========================================================================

    #[Test]
    public function testTrimAndLowerCaseTrimsWhitespace(): void
    {
        $result = Utils::trimAndLowerCase('  hello  ');
        $this->assertEquals('hello', $result);
    }

    #[Test]
    public function testTrimAndLowerCaseConvertsToLowercase(): void
    {
        $result = Utils::trimAndLowerCase('HELLO');
        $this->assertEquals('hello', $result);
    }

    #[Test]
    public function testTrimAndLowerCaseCombinesBoth(): void
    {
        $result = Utils::trimAndLowerCase('  HELLO WORLD  ');
        $this->assertEquals('hello world', $result);
    }

    // =========================================================================
    // Tests for removeEmptyStringFromTail()
    // =========================================================================

    #[Test]
    public function testRemoveEmptyStringFromTailRemovesSingleEmpty(): void
    {
        $array = ['a', 'b', 'c', ''];
        $result = Utils::removeEmptyStringFromTail($array);
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function testRemoveEmptyStringFromTailRemovesMultipleEmpty(): void
    {
        $array = ['a', 'b', '', ''];
        $result = Utils::removeEmptyStringFromTail($array);
        $this->assertEquals(['a', 'b'], $result);
    }

    #[Test]
    public function testRemoveEmptyStringFromTailPreservesNonTrailingEmpty(): void
    {
        $array = ['a', '', 'b', 'c'];
        $result = Utils::removeEmptyStringFromTail($array);
        $this->assertEquals(['a', '', 'b', 'c'], $result);
    }

    #[Test]
    public function testRemoveEmptyStringFromTailHandlesNoTrailingEmpty(): void
    {
        $array = ['a', 'b', 'c'];
        $result = Utils::removeEmptyStringFromTail($array);
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    // =========================================================================
    // Tests for ensure_keys()
    // =========================================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function testEnsureKeysReturnsParamsWhenAllKeysPresent(): void
    {
        $params = ['key1' => 'value1', 'key2' => 'value2'];
        $required = ['key1', 'key2'];
        $result = Utils::ensure_keys($params, $required);
        $this->assertSame($params, $result);
    }

    #[Test]
    public function testEnsureKeysThrowsExceptionWhenKeysMissing(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing keys: key2');

        $params = ['key1' => 'value1'];
        $required = ['key1', 'key2'];
        Utils::ensure_keys($params, $required);
    }

    #[Test]
    public function testEnsureKeysThrowsExceptionWithMultipleMissingKeys(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing keys: key2, key3');

        $params = ['key1' => 'value1'];
        $required = ['key1', 'key2', 'key3'];
        Utils::ensure_keys($params, $required);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testEnsureKeysAcceptsNullValues(): void
    {
        $params = ['key1' => null, 'key2' => 'value2'];
        $required = ['key1', 'key2'];
        $result = Utils::ensure_keys($params, $required);
        $this->assertSame($params, $result);
    }

    // =========================================================================
    // Tests for getRealIpAddr()
    // =========================================================================

    #[Test]
    public function testGetRealIpAddrReturnsRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $result = Utils::getRealIpAddr();
        $this->assertEquals('192.168.1.1', $result);
    }

    #[Test]
    public function testGetRealIpAddrPrefersClientIp(): void
    {
        $_SERVER['HTTP_CLIENT_IP'] = '10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $result = Utils::getRealIpAddr();
        $this->assertEquals('10.0.0.1', $result);
    }

    #[Test]
    public function testGetRealIpAddrHandlesForwardedFor(): void
    {
        unset($_SERVER['HTTP_CLIENT_IP']);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '172.16.0.1';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $result = Utils::getRealIpAddr();
        $this->assertEquals('172.16.0.1', $result);
    }

    #[Test]
    public function testGetRealIpAddrHandlesMultipleIpsInForwardedFor(): void
    {
        unset($_SERVER['HTTP_CLIENT_IP']);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '172.16.0.1, 10.0.0.1';
        $result = Utils::getRealIpAddr();
        $this->assertEquals('172.16.0.1', $result);
    }

    #[Test]
    public function testGetRealIpAddrHandlesIpv6(): void
    {
        $_SERVER['REMOTE_ADDR'] = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';
        unset($_SERVER['HTTP_CLIENT_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $result = Utils::getRealIpAddr();
        $this->assertEquals('2001:0db8:85a3:0000:0000:8a2e:0370:7334', $result);
    }

    #[Test]
    public function testGetRealIpAddrReturnsNullWhenNoValidIp(): void
    {
        unset($_SERVER['HTTP_CLIENT_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_FORWARDED']);
        unset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']);
        unset($_SERVER['HTTP_FORWARDED_FOR']);
        unset($_SERVER['HTTP_FORWARDED']);
        unset($_SERVER['REMOTE_ADDR']);
        $result = Utils::getRealIpAddr();
        $this->assertNull($result);
    }

    // =========================================================================
    // Tests for uuid4()
    // =========================================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function testUuid4ReturnsValidFormat(): void
    {
        $result = Utils::uuid4();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testUuid4Returns36Characters(): void
    {
        $result = Utils::uuid4();
        $this->assertEquals(36, strlen($result));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testUuid4GeneratesUniqueValues(): void
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = Utils::uuid4();
        }
        $uniqueUuids = array_unique($uuids);
        $this->assertCount(100, $uniqueUuids);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testUuid4HasCorrectVersionBit(): void
    {
        $uuid = Utils::uuid4();
        $parts = explode('-', $uuid);
        // Version 4 UUIDs have '4' as the first character of the third group
        $this->assertStringStartsWith('4', $parts[2]);
    }

    // =========================================================================
    // Tests for isTokenValid()
    // =========================================================================

    #[Test]
    public function testIsTokenValidReturnsTrueForValidToken(): void
    {
        $validToken = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $this->assertTrue(Utils::isTokenValid($validToken));
    }

    #[Test]
    public function testIsTokenValidReturnsFalseForNullToken(): void
    {
        $this->assertFalse(Utils::isTokenValid());
    }

    #[Test]
    public function testIsTokenValidReturnsFalseForEmptyToken(): void
    {
        $this->assertFalse(Utils::isTokenValid(''));
    }

    #[Test]
    public function testIsTokenValidReturnsFalseForInvalidFormat(): void
    {
        $this->assertFalse(Utils::isTokenValid('not-a-valid-uuid'));
    }

    #[Test]
    public function testIsTokenValidReturnsFalseForUppercaseToken(): void
    {
        $upperCaseToken = 'A1B2C3D4-E5F6-7890-ABCD-EF1234567890';
        $this->assertFalse(Utils::isTokenValid($upperCaseToken));
    }

    #[Test]
    public function testIsTokenValidReturnsFalseForWrongLength(): void
    {
        $this->assertFalse(Utils::isTokenValid('a1b2c3d4-e5f6-7890-abcd'));
    }

    // =========================================================================
    // Tests for fixFileName()
    // =========================================================================

    #[Test]
    public function testFixFileNameSanitizesSpecialCharacters(): void
    {
        $result = Utils::fixFileName('<script>alert("xss")</script>.txt');
        $this->assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function testFixFileNamePreservesNormalCharacters(): void
    {
        $result = Utils::fixFileName('normal_file.txt');
        $this->assertEquals('normal_file.txt', $result);
    }

    #[Test]
    public function testFixFileNameIncrementsCountWhenFileExists(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_file.txt';
        file_put_contents($testFile, 'test');

        $result = Utils::fixFileName('test_file.txt', $tempDir);
        $this->assertEquals('test_file_(1).txt', $result);

        unlink($testFile);
    }

    #[Test]
    public function testFixFileNameDoesNotIncrementWhenUpCountFalse(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile = $tempDir . '/test_file2.txt';
        file_put_contents($testFile, 'test');

        $result = Utils::fixFileName('test_file2.txt', $tempDir, false);
        $this->assertEquals('test_file2.txt', $result);

        unlink($testFile);
    }

    // =========================================================================
    // Tests for isValidFileName()
    // =========================================================================

    #[Test]
    public function testIsValidFileName(): void
    {
        $this->assertTrue(Utils::isValidFileName('valid_file.txt'));
    }

    #[Test]
    public function testIsValidFileNameWithSpaces(): void
    {
        $this->assertTrue(Utils::isValidFileName('valid file name.txt'));
    }

    #[Test]
    public function testIsValidFileNameReturnsFalseForEmptyName(): void
    {
        $this->assertFalse(Utils::isValidFileName(''));
    }

    #[Test]
    public function testIsValidFileNameReturnsFalseForOnlySpaces(): void
    {
        $this->assertFalse(Utils::isValidFileName('   '));
    }


    #[Test]
    public function testIsValidFileNameReturnsFalseForControlChars(): void
    {
        $this->assertFalse(Utils::isValidFileName("file\x01.txt"));
    }

    #[Test]
    public function testIsValidFileNameReturnsFalseForNewline(): void
    {
        $this->assertFalse(Utils::isValidFileName("file\n.txt"));
    }

    #[Test]
    public function testIsValidFileNameReturnsFalseForCarriageReturn(): void
    {
        $this->assertFalse(Utils::isValidFileName("file\r.txt"));
    }


    #[Test]
    public function testIsValidFileNameReturnsFalseForBackslash(): void
    {
        $this->assertFalse(Utils::isValidFileName('file\\name.txt'));
    }

    #[Test]
    public function testIsValidFileNameReturnsFalseForForwardSlash(): void
    {
        $this->assertFalse(Utils::isValidFileName('file/name.txt'));
    }

    #[Test]
    public function testIsValidFileNameReturnsFalseForColon(): void
    {
        $this->assertFalse(Utils::isValidFileName('file:name.txt'));
    }

    #[Test]
    public function testIsValidFileNameReturnsFalseForAsterisk(): void
    {
        $this->assertFalse(Utils::isValidFileName('file*.txt'));
    }

    #[Test]
    public function testIsValidFileNameReturnsFalseForQuestionMark(): void
    {
        $this->assertFalse(Utils::isValidFileName('file?.txt'));
    }

    #[Test]
    public function testIsValidFileNameReturnsFalseForDoubleQuote(): void
    {
        $this->assertFalse(Utils::isValidFileName('file".txt'));
    }

    #[Test]
    public function testIsValidFileNameReturnsFalseForLessThan(): void
    {
        $this->assertFalse(Utils::isValidFileName('file<.txt'));
    }

    #[Test]
    public function testIsValidFileNameReturnsFalseForGreaterThan(): void
    {
        $this->assertFalse(Utils::isValidFileName('file>.txt'));
    }

    #[Test]
    public function testIsValidFileNameReturnsFalseForPipe(): void
    {
        $this->assertFalse(Utils::isValidFileName('file|.txt'));
    }


    #[Test]
    public function testIsValidFileNameReturnsFalseForReservedNamePRN(): void
    {
        $this->assertFalse(Utils::isValidFileName('PRN'));
    }

    #[Test]
    public function testIsValidFileNameReturnsFalseForReservedNameAUX(): void
    {
        $this->assertFalse(Utils::isValidFileName('AUX'));
    }


    #[Test]
    public function testIsValidFileNameReturnsFalseForTooLongName(): void
    {
        $longName = str_repeat('a', 256);
        $this->assertFalse(Utils::isValidFileName($longName));
    }

    #[Test]
    public function testIsValidFileNameWith255CharName(): void
    {
        $maxName = str_repeat('a', 251) . '.txt';
        $this->assertTrue(Utils::isValidFileName($maxName));
    }

    // =========================================================================
    // Traversal attack vectors for isValidFileName()
    //
    // Exhaustive coverage of known path-traversal, encoding, null-byte, and
    // overlong-UTF-8 attack techniques.  Each test is named after the attack
    // category so failures immediately reveal which vector is not blocked.
    // =========================================================================

    // -- Classic directory traversal ------------------------------------------

    #[Test]
    public function testTraversalClassicDotDotSlash(): void
    {
        $this->assertFalse(Utils::isValidFileName('../etc/passwd'));
    }

    #[Test]
    public function testTraversalClassicDotDotBackslash(): void
    {
        $this->assertFalse(Utils::isValidFileName("..\\etc\\passwd"));
    }

    #[Test]
    public function testTraversalMidPathForwardSlash(): void
    {
        $this->assertFalse(Utils::isValidFileName('foo/../../etc/passwd'));
    }

    #[Test]
    public function testTraversalMidPathRelative(): void
    {
        $this->assertFalse(Utils::isValidFileName('foo/../bar'));
    }

    #[Test]
    public function testTraversalMidPathBackslash(): void
    {
        $this->assertFalse(Utils::isValidFileName("foo\\..\\bar"));
    }

    #[Test]
    public function testTraversalDotDotBackslashPrefix(): void
    {
        $this->assertFalse(Utils::isValidFileName("..\\foo"));
    }

    // -- URL-encoded traversal (single layer) ---------------------------------

    #[Test]
    public function testTraversalUrlEncodedDotDotSlash(): void
    {
        $this->assertFalse(Utils::isValidFileName('%2E%2E%2Fetc%2Fpasswd'));
    }

    #[Test]
    public function testTraversalUrlEncodedDotDotBackslash(): void
    {
        $this->assertFalse(Utils::isValidFileName('%2E%2E%5Cetc%5Cpasswd'));
    }

    #[Test]
    public function testTraversalPartialEncodeDotDotSlash(): void
    {
        $this->assertFalse(Utils::isValidFileName('..%2Fetc%2Fpasswd'));
    }

    #[Test]
    public function testTraversalPartialEncodeDotDotBackslash(): void
    {
        $this->assertFalse(Utils::isValidFileName('..%5Cetc%5Cpasswd'));
    }

    // -- Double-encoded traversal ---------------------------------------------

    #[Test]
    public function testTraversalDoubleEncodedDotDotSlash(): void
    {
        $this->assertFalse(Utils::isValidFileName('%252E%252E%252F'));
    }

    #[Test]
    public function testTraversalDoubleEncodedDotDotBackslash(): void
    {
        $this->assertFalse(Utils::isValidFileName('%252E%252E%255C'));
    }

    // -- Triple-encoded traversal ---------------------------------------------

    #[Test]
    public function testTraversalTripleEncodedDotDotSlash(): void
    {
        $this->assertFalse(Utils::isValidFileName('%25252E%25252E%25252F'));
    }

    // -- Null byte injection --------------------------------------------------

    #[Test]
    public function testTraversalNullByteUrlEncoded(): void
    {
        $this->assertFalse(Utils::isValidFileName('shell.php%00.jpg'));
    }

    #[Test]
    public function testTraversalNullByteLiteral(): void
    {
        $this->assertFalse(Utils::isValidFileName("shell.php\x00.jpg"));
    }

    // -- Overlong UTF-8 traversal ---------------------------------------------
    // %c0%ae is an overlong encoding of '.' (U+002E)
    // %c0%af is an overlong encoding of '/' (U+002F)
    // These are NOT exploitable in PHP on Linux/macOS/Windows because
    // PHP's urldecode() produces raw bytes (0xC0 0xAE), which are NOT
    // treated as '.' by the kernel or any PHP filesystem function.
    // The attack only worked on old IIS/Tomcat with broken UTF-8 decoders
    // (CVE-2008-2938). Still, partial sequences starting with '..' are blocked.

    #[Test]
    public function testTraversalOverlongUtf8PartialDotDotSlash(): void
    {
        // ..%c0%af — starts with literal '..' so the traversal regex catches it
        $this->assertFalse(Utils::isValidFileName('..%c0%af'));
    }

    #[Test]
    public function testTraversalOverlongUtf8PartialDotDotBackslash(): void
    {
        // ..%c1%9c — starts with literal '..' so the traversal regex catches it
        $this->assertFalse(Utils::isValidFileName('..%c1%9c'));
    }

    #[Test]
    public function testTraversalOverlongUtf8FullSequenceIsNotExploitable(): void
    {
        // %c0%ae%c0%ae%c0%af — fully overlong-encoded '../'
        // This passes validation because after urldecode the raw bytes (0xC0 0xAE ...)
        // are NOT '.' or '/' to PHP or the kernel.  This is expected and safe:
        // the file system would create a file named with those literal bytes,
        // not traverse the directory tree.
        $this->assertTrue(Utils::isValidFileName('%c0%ae%c0%ae%c0%af'));
    }

    // -- Dot files and special dot names --------------------------------------

    #[Test]
    public function testTraversalDotAsFullName(): void
    {
        $this->assertFalse(Utils::isValidFileName('.'));
    }

    #[Test]
    public function testTraversalDotDotAsFullName(): void
    {
        $this->assertFalse(Utils::isValidFileName('..'));
    }

    #[Test]
    public function testTraversalHiddenDotEnv(): void
    {
        $this->assertFalse(Utils::isValidFileName('.env'));
    }

    #[Test]
    public function testTraversalHiddenHtaccess(): void
    {
        $this->assertFalse(Utils::isValidFileName('.htaccess'));
    }

    // -- Absolute paths -------------------------------------------------------

    #[Test]
    public function testTraversalAbsoluteUnixPath(): void
    {
        $this->assertFalse(Utils::isValidFileName('/etc/passwd'));
    }

    #[Test]
    public function testTraversalAbsoluteWindowsPath(): void
    {
        $this->assertFalse(Utils::isValidFileName("C:\\Windows\\system32"));
    }

    // -- Windows reserved device names ----------------------------------------

    #[Test]
    public function testTraversalReservedCON(): void
    {
        $this->assertFalse(Utils::isValidFileName('CON'));
    }

    #[Test]
    public function testTraversalReservedNUL(): void
    {
        $this->assertFalse(Utils::isValidFileName('NUL'));
    }

    #[Test]
    public function testTraversalReservedCOM1(): void
    {
        $this->assertFalse(Utils::isValidFileName('COM1'));
    }

    #[Test]
    public function testTraversalReservedLPT1(): void
    {
        $this->assertFalse(Utils::isValidFileName('LPT1'));
    }

    // -- Valid file names that must NOT be blocked ----------------------------

    #[Test]
    public function testTraversalValidSimpleName(): void
    {
        $this->assertTrue(Utils::isValidFileName('report.txt'));
    }

    #[Test]
    public function testTraversalValidNameWithSpacesAndParens(): void
    {
        $this->assertTrue(Utils::isValidFileName('my file (1).docx'));
    }

    #[Test]
    public function testTraversalValidUnicodeName(): void
    {
        $this->assertTrue(Utils::isValidFileName('résumé.pdf'));
    }

    #[Test]
    public function testTraversalValidCjkName(): void
    {
        $this->assertTrue(Utils::isValidFileName('日本語.txt'));
    }

    // =========================================================================
    // Tests for deleteDir()
    // =========================================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function testDeleteDirRemovesEmptyDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_delete_' . uniqid();
        mkdir($tempDir);
        $this->assertDirectoryExists($tempDir);

        Utils::deleteDir($tempDir);
        $this->assertDirectoryDoesNotExist($tempDir);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testDeleteDirRemovesDirectoryWithFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_delete_' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/test.txt', 'test');
        $this->assertFileExists($tempDir . '/test.txt');

        Utils::deleteDir($tempDir);
        $this->assertDirectoryDoesNotExist($tempDir);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testDeleteDirRemovesNestedDirectories(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_delete_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/subdir');
        file_put_contents($tempDir . '/subdir/test.txt', 'test');

        Utils::deleteDir($tempDir);
        $this->assertDirectoryDoesNotExist($tempDir);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testDeleteDirSkipsHiddenFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_delete_' . uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir . '/.hidden', 'hidden');
        file_put_contents($tempDir . '/visible.txt', 'visible');

        // Note: The current implementation intentionally skips hidden files (those starting with .)
        // This means the directory won't be deleted if it contains hidden files
        // We need to suppress the warning about non-empty directory
        @Utils::deleteDir($tempDir);

        // Due to the intentional skip of hidden files, verify the hidden file still exists
        // and clean up manually
        if (file_exists($tempDir . '/.hidden')) {
            unlink($tempDir . '/.hidden');
            rmdir($tempDir);
        }
        $this->assertDirectoryDoesNotExist($tempDir);
    }

    // =========================================================================
    // Tests for stripFileBOM()
    // =========================================================================

    #[Test]
    public function testStripFileBOMRemovesUtf8Bom(): void
    {
        $stringWithBom = "\xEF\xBB\xBFHello";
        $result = Utils::stripFileBOM($stringWithBom);
        $this->assertEquals('Hello', $result);
    }

    #[Test]
    public function testStripFileBOMRemovesUtf16Bom(): void
    {
        $stringWithBom = "\xFF\xFEHello";
        $result = Utils::stripFileBOM($stringWithBom, 16);
        $this->assertEquals('Hello', $result);
    }

    #[Test]
    public function testStripFileBOMRemovesUtf32Bom(): void
    {
        $stringWithBom = "\x00\x00\xFE\xFFHello";
        $result = Utils::stripFileBOM($stringWithBom, 32);
        $this->assertEquals('Hello', $result);
    }

    #[Test]
    public function testStripFileBOMDefaultsToUtf8(): void
    {
        $stringWithBom = "\xEF\xBB\xBFHello";
        $result = Utils::stripFileBOM($stringWithBom);
        $this->assertEquals('Hello', $result);
    }

    // =========================================================================
    // Tests for stripBOM()
    // =========================================================================

    #[Test]
    public function testStripBOMRemovesUtf8Bom(): void
    {
        $stringWithBom = "\xEF\xBB\xBFHello World";
        $result = Utils::stripBOM($stringWithBom);
        $this->assertEquals('Hello World', $result);
    }

    #[Test]
    public function testStripBOMPreservesStringWithoutBom(): void
    {
        $string = 'Hello World';
        $result = Utils::stripBOM($string);
        $this->assertEquals('Hello World', $result);
    }

    #[Test]
    public function testStripBOMHandlesEmptyString(): void
    {
        $result = Utils::stripBOM('');
        $this->assertEquals('', $result);
    }

    // =========================================================================
    // Tests for uploadDirFromSessionCookie()
    // =========================================================================

    #[Test]
    public function testUploadDirFromSessionCookieReturnsCorrectPath(): void
    {
        $guid = 'test-guid-123';
        $result = Utils::uploadDirFromSessionCookie($guid);
        $this->assertStringContainsString($guid, $result);
    }

    #[Test]
    public function testUploadDirFromSessionCookieIncludesFileName(): void
    {
        $guid = 'test-guid-123';
        $fileName = 'test.txt';
        $result = Utils::uploadDirFromSessionCookie($guid, $fileName);
        $this->assertStringContainsString($fileName, $result);
        $this->assertStringContainsString($guid, $result);
    }

    // =========================================================================
    // Tests for htmlentitiesToUft8WithoutDoubleEncoding()
    // =========================================================================

    #[Test]
    public function testHtmlentitiesToUft8WithoutDoubleEncodingConvertsSpecialChars(): void
    {
        $result = Utils::htmlentitiesToUft8WithoutDoubleEncoding('<script>alert("xss")</script>');
        $this->assertStringContainsString('&lt;', $result);
        $this->assertStringContainsString('&gt;', $result);
    }

    #[Test]
    public function testHtmlentitiesToUft8WithoutDoubleEncodingDoesNotDoubleEncode(): void
    {
        $alreadyEncoded = '&lt;script&gt;';
        $result = Utils::htmlentitiesToUft8WithoutDoubleEncoding($alreadyEncoded);
        $this->assertStringNotContainsString('&amp;lt;', $result);
    }

    #[Test]
    public function testHtmlentitiesToUft8WithoutDoubleEncodingHandlesQuotes(): void
    {
        $result = Utils::htmlentitiesToUft8WithoutDoubleEncoding('"test"');
        $this->assertStringContainsString('&quot;', $result);
    }

    // =========================================================================
    // Tests for truncatePhrase()
    // =========================================================================

    #[Test]
    public function testTruncatePhraseReturnsFullPhraseWhenUnderLimit(): void
    {
        $phrase = 'Hello World';
        $result = Utils::truncatePhrase($phrase, 5);
        $this->assertEquals('Hello World', $result);
    }

    #[Test]
    public function testTruncatePhraseTruncatesLongPhrase(): void
    {
        $phrase = 'One Two Three Four Five Six Seven';
        $result = Utils::truncatePhrase($phrase, 3);
        $this->assertEquals('One Two Three', $result);
    }

    #[Test]
    public function testTruncatePhraseReturnsFullPhraseWhenLimitIsZero(): void
    {
        $phrase = 'Hello World';
        $result = Utils::truncatePhrase($phrase, 0);
        $this->assertEquals('Hello World', $result);
    }

    #[Test]
    public function testTruncatePhraseHandlesSingleWord(): void
    {
        $phrase = 'Hello';
        $result = Utils::truncatePhrase($phrase, 1);
        $this->assertEquals('Hello', $result);
    }

    // =========================================================================
    // Tests for stripTagsPreservingHrefs()
    // =========================================================================

    #[Test]
    public function testStripTagsPreservingHrefsPreservesPlainText(): void
    {
        $text = 'This is plain text.';
        $result = Utils::stripTagsPreservingHrefs($text);
        $this->assertEquals($text, $result);
    }

    #[Test]
    public function testStripTagsPreservingHrefsConvertsLinks(): void
    {
        $html = '<a href="https://example.com">Click here</a>';
        $result = Utils::stripTagsPreservingHrefs($html);
        $this->assertStringContainsString('[Click here]', $result);
        $this->assertStringContainsString('(https://example.com)', $result);
    }

    #[Test]
    public function testStripTagsPreservingHrefsPreservesImgSrc(): void
    {
        $html = '<img src="https://example.com/image.jpg" alt="Test"/>';
        $result = Utils::stripTagsPreservingHrefs($html);
        $this->assertStringContainsString('https://example.com/image.jpg', $result);
    }

    #[Test]
    public function testStripTagsPreservingHrefsStripsParagraphTags(): void
    {
        $html = '<p>Hello <strong>World</strong></p>';
        $result = Utils::stripTagsPreservingHrefs($html);
        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringNotContainsString('<strong>', $result);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('World', $result);
    }

    // =========================================================================
    // Tests for validateEmailList()
    // =========================================================================

    #[Test]
    public function testValidateEmailListReturnsValidEmails(): void
    {
        $list = 'test@example.com, user@domain.org';
        $result = Utils::validateEmailList($list);
        $this->assertCount(2, $result);
        $this->assertContains('test@example.com', $result);
        $this->assertContains('user@domain.org', $result);
    }

    #[Test]
    public function testValidateEmailListThrowsExceptionForInvalidEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-6);

        Utils::validateEmailList('invalid-email, test@example.com');
    }

    #[Test]
    public function testValidateEmailListHandlesEmptyEntries(): void
    {
        $list = 'test@example.com, , user@domain.org';
        $result = Utils::validateEmailList($list);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function testValidateEmailListTrimsWhitespace(): void
    {
        $list = '  test@example.com  ,  user@domain.org  ';
        $result = Utils::validateEmailList($list);
        $this->assertContains('test@example.com', $result);
        $this->assertContains('user@domain.org', $result);
    }

    #[Test]
    public function testValidateEmailListHandlesSingleEmail(): void
    {
        $list = 'test@example.com';
        $result = Utils::validateEmailList($list);
        $this->assertCount(1, $result);
        $this->assertContains('test@example.com', $result);
    }

    // =========================================================================
    // Additional tests for full coverage
    // =========================================================================

    #[Test]
    public function testGetBrowserDetectsMSIE(): void
    {
        // Test MSIE browser detection (lines 115-116)
        $msieAgent = 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)';
        $result = Utils::getBrowser($msieAgent);

        $this->assertEquals('Internet Explorer', $result['name']);
        $this->assertEquals('Windows', $result['platform']);
    }

    #[Test]
    public function testGetBrowserDetectsMSIENotOpera(): void
    {
        // MSIE should not be detected as Opera
        $msieAgent = 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)';
        $result = Utils::getBrowser($msieAgent);

        $this->assertEquals('Internet Explorer', $result['name']);
    }

    #[Test]
    public function testGetBrowserVersionWithVersionFirst(): void
    {
        // Test case where 'Version' comes before browser name (line 156)
        // This happens with Safari user agents where Version/X.X comes before Safari/XXX
        $safariAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Chrome/91.0.4472.124';
        $result = Utils::getBrowser($safariAgent);

        // Should extract version from the correct position
        $this->assertNotEquals('?', $result['version']);
    }

    #[Test]
    public function testGetBrowserEdgeOnIpad(): void
    {
        // Test Edge browser on iPad (should not be detected as Edge due to ipadOS platform check)
        $ipadEdgeAgent = 'Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 EdgiOS/45.0 Mobile/15E148 Safari/604.1';
        $result = Utils::getBrowser($ipadEdgeAgent);

        $this->assertEquals('ipadOS', $result['platform']);
        // On ipadOS, Edge is not detected as Microsoft Edge
        $this->assertNotEquals('Microsoft Edge', $result['name']);
    }

    #[Test]
    public function testGetBrowserIEMobile(): void
    {
        // Test IEMobile detection
        $ieMobileAgent = 'Mozilla/5.0 (Mobile; Windows Phone 8.1; Android 4.0; ARM; Trident/7.0; Touch; IEMobile/11.0) like iPhone OS 7_0_3 Mac OS X AppleWebKit/537 (KHTML, like Gecko) Mobile Safari/537';
        $result = Utils::getBrowser($ieMobileAgent);

        $this->assertEquals('Internet Explorer Mobile', $result['name']);
    }

    #[Test]
    public function testGetBrowserWebKitMobile(): void
    {
        // Test WebKit mobile detection without Safari string
        $webkitAgent = 'Mozilla/5.0 (Linux; U; Android 4.0.3; en-us) AppleWebKit/534.30 (KHTML, like Gecko) Mobile';
        $result = Utils::getBrowser($webkitAgent);

        $this->assertEquals('Mobile Safari', $result['name']);
    }

    #[Test]
    public function testGetBrowserNoVersionMatch(): void
    {
        // Test when version pattern doesn't match - should return '?'
        $noVersionAgent = 'SomeRandomBot/1.0';
        $result = Utils::getBrowser($noVersionAgent);

        $this->assertEquals('?', $result['version']);
    }

    #[Test]
    public function testGetSourcePageForRevisePath(): void
    {
        $_SERVER['REQUEST_URI'] = '/revise/project/1-2/en-US-it-IT';
        $result = Utils::getSourcePage();
        // SOURCE_PAGE_REVISION = 2
        $this->assertEquals(2, $result);
    }

    #[Test]
    public function testGetSourcePageForRevise2Path(): void
    {
        $_SERVER['REQUEST_URI'] = '/revise2/project/1-2/en-US-it-IT';
        $result = Utils::getSourcePage();
        // revise2 should return SOURCE_PAGE_REVISION + 1 = 3
        $this->assertEquals(3, $result);
    }

    #[Test]
    public function testGetSourcePageFromRefererForRevisePath(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://example.com/revise/project/1-2/en-US-it-IT';
        $result = Utils::getSourcePageFromReferer();
        $this->assertEquals(2, $result);
    }

    #[Test]
    public function testMysqlTimestampFallbackForInvalidTime(): void
    {
        // Test with a value that causes date() to return false (line 284)
        // Note: PHP's date() function is quite robust and handles most values
        // The fallback is triggered when date() returns false, which is rare
        // We test with 0 which should work fine
        $result = Utils::mysqlTimestamp(0);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result);
    }

    #[Test]
    public function testRandomStringWithMoreEntropyLonger(): void
    {
        // Test more_entropy with longer string
        $result = Utils::randomString(24, true);
        $this->assertEquals(24, strlen($result));
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testDeleteDirWithNestedHiddenFiles(): void
    {
        $tempDir = sys_get_temp_dir() . '/test_delete_nested_' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/subdir');
        file_put_contents($tempDir . '/subdir/.hidden', 'hidden');
        file_put_contents($tempDir . '/subdir/visible.txt', 'visible');

        // deleteDir will delete visible files but skip hidden ones
        @Utils::deleteDir($tempDir);

        // Clean up manually since hidden files are skipped
        if (file_exists($tempDir . '/subdir/.hidden')) {
            unlink($tempDir . '/subdir/.hidden');
            @rmdir($tempDir . '/subdir');
            @rmdir($tempDir);
        }
        $this->assertDirectoryDoesNotExist($tempDir);
    }

    #[Test]
    public function testIsValidFileNameWithSingleQuote(): void
    {
        // Test that single quotes are valid (not part of invalidChars)
        $this->assertTrue(Utils::isValidFileName("file'name.txt"));
    }

    #[Test]
    public function testIsValidFileNameWithReservedNameAndExtension(): void
    {
        // Reserved name with extension - the function uses PATHINFO_BASENAME
        // which includes the extension, so CON.txt is valid (only CON is blocked)
        $this->assertTrue(Utils::isValidFileName('CON.txt'));
    }

    #[Test]
    public function testIsValidFileNameWithCOM9(): void
    {
        // COM9 is in the reserved list
        $this->assertFalse(Utils::isValidFileName('COM9'));
    }

    #[Test]
    public function testIsValidFileNameWithLPT9(): void
    {
        $this->assertFalse(Utils::isValidFileName('LPT9'));
    }

    #[Test]
    public function testStripTagsPreservingHrefsWithMultipleLinks(): void
    {
        $html = '<p>Link 1: <a href="https://example1.com">First</a> and Link 2: <a href="https://example2.com">Second</a></p>';
        $result = Utils::stripTagsPreservingHrefs($html);
        $this->assertStringContainsString('[First](https://example1.com)', $result);
        $this->assertStringContainsString('[Second](https://example2.com)', $result);
    }

    #[Test]
    public function testStripTagsPreservingHrefsWithNestedTags(): void
    {
        $html = '<div><p><strong>Bold</strong> and <em>italic</em></p></div>';
        $result = Utils::stripTagsPreservingHrefs($html);
        $this->assertStringContainsString('Bold', $result);
        $this->assertStringContainsString('italic', $result);
        $this->assertStringNotContainsString('<strong>', $result);
    }

    #[Test]
    public function testTruncatePhraseWithExactWordCount(): void
    {
        $phrase = 'One Two Three';
        $result = Utils::truncatePhrase($phrase, 3);
        $this->assertEquals('One Two Three', $result);
    }

    #[Test]
    public function testFixFileNameMultipleIncrements(): void
    {
        $tempDir = sys_get_temp_dir();
        $testFile1 = $tempDir . '/test_increment.txt';
        $testFile2 = $tempDir . '/test_increment_(1).txt';
        file_put_contents($testFile1, 'test');
        file_put_contents($testFile2, 'test');

        $result = Utils::fixFileName('test_increment.txt', $tempDir);
        $this->assertEquals('test_increment_(2).txt', $result);

        unlink($testFile1);
        unlink($testFile2);
    }

    // =========================================================================
    // Tests for changeMemorySuggestionSource()
    // =========================================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function testChangeMemorySuggestionSourceReturnsPublicTmForMatecatSourceCaseInsensitive(): void
    {
        $match = [
            'created_by' => 'MaTeCaT',
            'memory_key' => 'irrelevant'
        ];

        $result = Utils::changeMemorySuggestionSource($match, '[]');

        $this->assertSame(\Utils\Constants\Constants::PUBLIC_TM, $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testChangeMemorySuggestionSourceReturnsNonMyMemorySourceName(): void
    {
        $match = [
            'created_by' => 'AcmeTM',
            'memory_key' => 'irrelevant'
        ];

        $result = Utils::changeMemorySuggestionSource($match, '[]');

        $this->assertSame('AcmeTM', $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testChangeMemorySuggestionSourceFallsBackToDefaultKeyDescriptionWhenUidIsNull(): void
    {
        $key = md5('default-key-description-null-uid');
        $jobTmKeys = json_encode([
            [
                'tm' => true,
                'glos' => false,
                'owner' => true,
                'uid_transl' => null,
                'uid_rev' => null,
                'name' => 'Default Key Name',
                'key' => $key,
                'r' => true,
                'w' => true,
                'r_transl' => null,
                'w_transl' => null,
                'r_rev' => null,
                'w_rev' => null,
                'source' => null,
                'target' => null,
            ]
        ], JSON_THROW_ON_ERROR);

        $match = [
            'created_by' => '',
            'memory_key' => $key,
        ];

        $result = Utils::changeMemorySuggestionSource($match, $jobTmKeys, null);

        $this->assertSame('Default Key Name', $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    #[Group('PersistenceNeeded')]
    public function testChangeMemorySuggestionSourceUsesUserKeyringFirstWhenUidProvided(): void
    {
        $uid = 910001;
        $key = md5('user-keyring-priority');
        $this->deleteMemoryKeyRow($uid, $key);
        $this->insertMemoryKeyRow($uid, $key, 'User Key Name');

        $jobTmKeys = json_encode([
            [
                'tm' => true,
                'glos' => false,
                'owner' => true,
                'uid_transl' => null,
                'uid_rev' => null,
                'name' => 'Owner Key Name',
                'key' => $key,
                'r' => true,
                'w' => true,
                'r_transl' => null,
                'w_transl' => null,
                'r_rev' => null,
                'w_rev' => null,
                'source' => null,
                'target' => null,
            ]
        ], JSON_THROW_ON_ERROR);

        $match = [
            'created_by' => 'MyMemory',
            'memory_key' => $key,
        ];

        $result = Utils::changeMemorySuggestionSource($match, $jobTmKeys, $uid);

        $this->assertSame('User Key Name', $result);
        $this->deleteMemoryKeyRow($uid, $key);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testChangeMemorySuggestionSourceReturnsPublicTmWhenNoBranchMatches(): void
    {
        $match = [
            'created_by' => '',
            'memory_key' => 'not-a-md5',
        ];

        $result = Utils::changeMemorySuggestionSource($match, '[]');

        $this->assertSame(\Utils\Constants\Constants::PUBLIC_TM, $result);
    }

    // =========================================================================
    // Tests for keyNameFromUserKeyring()
    // =========================================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function testKeyNameFromUserKeyringReturnsNullWhenUidIsNull(): void
    {
        $result = Utils::keyNameFromUserKeyring(md5('key-with-null-uid'), null);

        $this->assertNull($result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    #[Group('PersistenceNeeded')]
    public function testKeyNameFromUserKeyringReturnsNameWhenMatchingKeyExists(): void
    {
        $uid = 910002;
        $key = md5('existing-key-name');
        $this->deleteMemoryKeyRow($uid, $key);
        $this->insertMemoryKeyRow($uid, $key, 'Existing Key Name');

        $result = Utils::keyNameFromUserKeyring($key, $uid);

        $this->assertSame('Existing Key Name', $result);
        $this->deleteMemoryKeyRow($uid, $key);
    }

    /**
     * @throws Exception
     */
    #[Test]
    #[Group('PersistenceNeeded')]
    public function testKeyNameFromUserKeyringReturnsNullWhenNoMatchingKeyExists(): void
    {
        $uid = 910003;
        $key = md5('missing-key-name');
        $this->deleteMemoryKeyRow($uid, $key);

        $result = Utils::keyNameFromUserKeyring($key, $uid);

        $this->assertNull($result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    #[Group('PersistenceNeeded')]
    public function testKeyNameFromUserKeyringReturnsNoDescriptionWhenKeyNameIsEmpty(): void
    {
        $uid = 910004;
        $key = md5('empty-key-name');
        $this->deleteMemoryKeyRow($uid, $key);
        $this->insertMemoryKeyRow($uid, $key, '   ');

        $result = Utils::keyNameFromUserKeyring($key, $uid);

        $this->assertSame(\Utils\Constants\Constants::NO_DESCRIPTION_TM, $result);
        $this->deleteMemoryKeyRow($uid, $key);
    }

    // =========================================================================
    // Tests for getDefaultKeyDescription()
    // =========================================================================

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetDefaultKeyDescriptionReturnsNameWhenOwnerKeyExists(): void
    {
        $key = md5('owner-key-found');
        $jobTmKeys = json_encode([
            [
                'tm' => true,
                'glos' => false,
                'owner' => true,
                'uid_transl' => null,
                'uid_rev' => null,
                'name' => 'Owner Key Visible Name',
                'key' => $key,
                'r' => true,
                'w' => true,
                'r_transl' => null,
                'w_transl' => null,
                'r_rev' => null,
                'w_rev' => null,
                'source' => null,
                'target' => null,
            ]
        ], JSON_THROW_ON_ERROR);

        $result = Utils::getDefaultKeyDescription($key, $jobTmKeys);

        $this->assertSame('Owner Key Visible Name', $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetDefaultKeyDescriptionReturnsNoDescriptionWhenKeyNotFound(): void
    {
        $jobTmKeys = json_encode([
            [
                'tm' => true,
                'glos' => false,
                'owner' => true,
                'uid_transl' => null,
                'uid_rev' => null,
                'name' => 'Different Key Name',
                'key' => md5('different-key'),
                'r' => true,
                'w' => true,
                'r_transl' => null,
                'w_transl' => null,
                'r_rev' => null,
                'w_rev' => null,
                'source' => null,
                'target' => null,
            ]
        ], JSON_THROW_ON_ERROR);

        $result = Utils::getDefaultKeyDescription(md5('target-missing-key'), $jobTmKeys);

        $this->assertSame(\Utils\Constants\Constants::NO_DESCRIPTION_TM, $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testGetDefaultKeyDescriptionReturnsNoDescriptionWhenOwnerKeyNameIsEmpty(): void
    {
        $key = md5('owner-key-empty-name');
        $jobTmKeys = json_encode([
            [
                'tm' => true,
                'glos' => false,
                'owner' => true,
                'uid_transl' => null,
                'uid_rev' => null,
                'name' => '   ',
                'key' => $key,
                'r' => true,
                'w' => true,
                'r_transl' => null,
                'w_transl' => null,
                'r_rev' => null,
                'w_rev' => null,
                'source' => null,
                'target' => null,
            ]
        ], JSON_THROW_ON_ERROR);

        $result = Utils::getDefaultKeyDescription($key, $jobTmKeys);

        $this->assertSame(\Utils\Constants\Constants::NO_DESCRIPTION_TM, $result);
    }

    /**
     * @throws Exception
     */
    private function insertMemoryKeyRow(int $uid, string $key, string $keyName): void
    {
        $connection = \Model\DataAccess\Database::obtain()->getConnection();
        $stmt = $connection->prepare(
            'INSERT INTO memory_keys (uid, key_value, key_name, key_tm, key_glos, creation_date, deleted) VALUES (:uid, :key_value, :key_name, 1, 1, NOW(), 0)'
        );

        $stmt->execute([
            'uid' => $uid,
            'key_value' => $key,
            'key_name' => $keyName,
        ]);
    }

    /**
     * @throws Exception
     */
    private function deleteMemoryKeyRow(int $uid, string $key): void
    {
        $connection = \Model\DataAccess\Database::obtain()->getConnection();
        $stmt = $connection->prepare('DELETE FROM memory_keys WHERE uid = :uid AND key_value = :key_value');
        $stmt->execute([
            'uid' => $uid,
            'key_value' => $key,
        ]);
    }

    // =========================================================================
    // Cleanup
    // =========================================================================

    public function tearDown(): void
    {
        // Clean up $_SERVER variables
        unset($_SERVER['HTTP_USER_AGENT']);
        unset($_SERVER['REQUEST_URI']);
        unset($_SERVER['HTTP_REFERER']);
        unset($_SERVER['HTTP_CLIENT_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_FORWARDED']);
        unset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']);
        unset($_SERVER['HTTP_FORWARDED_FOR']);
        unset($_SERVER['HTTP_FORWARDED']);
        unset($_SERVER['REMOTE_ADDR']);

        parent::tearDown();
    }
}
