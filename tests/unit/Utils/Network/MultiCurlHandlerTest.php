<?php

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Network\MultiCurlHandler;
use Utils\Registry\AppConfig;

class UtilsNetworkMultiCurlHandlerTest extends AbstractTest
{

    #[Test]
    public function constructionCreatesValidInstance(): void
    {
        $handler = new MultiCurlHandler();

        $this->assertInstanceOf(MultiCurlHandler::class, $handler);
    }

    #[Test]
    public function createResourceReturnsNonEmptyTokenHash(): void
    {
        $handler = new MultiCurlHandler();

        $token = $handler->createResource('http://example.invalid/', [CURLOPT_RETURNTRANSFER => true]);

        $this->assertIsString($token);
        $this->assertNotSame('', $token);
    }

    #[Test]
    public function addResourceWithExistingHandleReturnsToken(): void
    {
        $curlHandle = curl_init();
        $handler = new MultiCurlHandler();

        $token = $handler->addResource($curlHandle);

        $this->assertIsString($token);
        $this->assertNotSame('', $token);
    }

    #[Test]
    public function clearResetsInternalState(): void
    {
        $handler = new MultiCurlHandler();
        $token = $handler->createResource('http://example.invalid/', [CURLOPT_RETURNTRANSFER => true], 'token-1');
        $handler->setRequestHeader($token);

        $handler->clear();

        $this->assertSame([], $this->readProperty($handler, 'curl_headers_requests'));
        $this->assertSame([], $this->readProperty($handler, 'curl_options_requests'));
        $this->assertSame([], $this->readProperty($handler, 'multi_curl_results'));
        $this->assertSame([], $this->readProperty($handler, 'multi_curl_info'));
        $this->assertSame([], $this->readProperty($handler, 'multi_curl_log'));
        $this->assertNull($this->readProperty($handler, 'multi_handler'));
    }

    #[Test]
    public function multiCurlCloseAllNullsTheHandler(): void
    {
        $handler = new MultiCurlHandler();

        $handler->multiCurlCloseAll();

        $this->assertNull($this->readProperty($handler, 'multi_handler'));
    }

    #[Test]
    public function getSingleContentReturnsNullForUnknownToken(): void
    {
        $handler = new MultiCurlHandler();

        $this->assertNull($handler->getSingleContent('missing-token'));
    }

    #[Test]
    public function getSingleInfoReturnsNullForUnknownToken(): void
    {
        $handler = new MultiCurlHandler();

        $this->assertNull($handler->getSingleInfo('missing-token'));
    }

    #[Test]
    public function getErrorsReturnsEmptyArrayWhenNoExecutionWasDone(): void
    {
        $handler = new MultiCurlHandler();

        $this->assertSame([], $handler->getErrors());
    }

    #[Test]
    public function getAllContentsAppliesCallback(): void
    {
        $handler = new MultiCurlHandler();
        $this->writeProperty($handler, 'multi_curl_results', [
            'a' => 'first',
            'b' => 'second',
        ]);

        $result = $handler->getAllContents(static fn (string $item): string => strtoupper($item));

        $this->assertSame([
            'a' => 'FIRST',
            'b' => 'SECOND',
        ], $result);
    }

    #[Test]
    public function setRequestHeaderReturnsSelfForFluentChaining(): void
    {
        $handler = new MultiCurlHandler();
        $token = $handler->createResource('http://example.invalid/', [CURLOPT_RETURNTRANSFER => true], 'token-1');

        $result = $handler->setRequestHeader($token);

        $this->assertSame($handler, $result);
    }

    #[Test]
    public function multiExecThrowsRuntimeExceptionWhenHandlerIsNull(): void
    {
        $handler = new MultiCurlHandler();
        $handler->clear();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Multi curl handler is not initialized');

        $handler->multiExec();
    }

    #[Test]
    public function addResourceThrowsRuntimeExceptionWhenHandlerIsNull(): void
    {
        $handler = new MultiCurlHandler();
        $handler->clear();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Multi curl handler is not initialized');

        $handler->addResource(curl_init());
    }

    // ─── Integration tests (require network) ───────────────────────────

    #[Test]
    #[Group('ExternalServices')]
    #[Group('HTTP')]
    public function createSingleResourceAndExec(): void
    {
        $options = [
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
            CURLOPT_CONNECTTIMEOUT => 2,
        ];

        $mh = new MultiCurlHandler();

        $tokenHash = $mh->createResource('https://www.google.com/', $options);
        $this->assertNotEmpty($tokenHash);

        $mh->multiExec();

        $singleContent = $mh->getSingleContent($tokenHash);
        $multiContent  = $mh->getAllContents();

        $this->assertNotEmpty($singleContent);
        $this->assertNotEmpty($multiContent);
        $this->assertEquals($singleContent, $multiContent[$tokenHash]);
    }

    #[Test]
    #[Group('ExternalServices')]
    #[Group('HTTP')]
    public function addSingleResourceAndExec(): void
    {
        $options = [
            CURLOPT_URL            => 'https://www.google.com/',
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
            CURLOPT_CONNECTTIMEOUT => 2,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $options);

        $mh = new MultiCurlHandler();

        $tokenHash = $mh->addResource($ch);
        $this->assertNotEmpty($tokenHash);

        $mh->multiExec();

        $singleContent = $mh->getSingleContent($tokenHash);
        $multiContent  = $mh->getAllContents();

        $this->assertNotEmpty($singleContent);
        $this->assertNotEmpty($multiContent);
        $this->assertEquals($singleContent, $multiContent[$tokenHash]);
    }

    #[Test]
    #[Group('ExternalServices')]
    #[Group('HTTP')]
    public function multipleParallelCurlRequests(): void
    {
        $options = [
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
            CURLOPT_CONNECTTIMEOUT => 2,
        ];

        $mh = new MultiCurlHandler();

        $tokenHash1 = $mh->createResource('https://www.google.com/', $options);
        $this->assertNotEmpty($tokenHash1);

        $tokenHash2 = $mh->createResource('https://it.yahoo.com/', $options);
        $this->assertNotEmpty($tokenHash2);

        $tokenHash3 = $mh->createResource('https://www.bing.com/', $options);
        $this->assertNotEmpty($tokenHash3);

        $tokenHash4 = $mh->createResource('https://www.translated.net/', $options);
        $this->assertNotEmpty($tokenHash4);

        $mh->multiExec();

        $multiContent = $mh->getAllContents();
        $this->assertNotEmpty($multiContent);

        $singleContent = $mh->getSingleContent($tokenHash2);
        $this->assertNotEmpty($singleContent);

        foreach ($multiContent as $hash => $result) {
            $this->assertEquals($mh->getSingleContent($hash), $result);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function readProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionClass($object);
        $refProperty = $reflection->getProperty($property);

        return $refProperty->getValue($object);
    }

    private function writeProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($object);
        $refProperty = $reflection->getProperty($property);
        $refProperty->setValue($object, $value);
    }
}
