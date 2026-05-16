<?php

namespace unit\Utils\OutsourceTo;

use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Utils\OutsourceTo\Translated;
use Utils\Shop\Cart;
use Utils\Shop\ItemHTSQuoteJob;

class TranslatedTest extends TestCase
{
    /**
     * @param object             $object
     * @param string             $methodName
     * @param array<int, mixed>  $parameters
     *
     * @return mixed
     */
    private static function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        return (new ReflectionMethod($object, $methodName))->invoke($object, ...$parameters);
    }

    private static function createTranslatedStub(): Translated
    {
        return (new ReflectionClass(Translated::class))->newInstanceWithoutConstructor();
    }

    private function setProperty(object $obj, string $name, mixed $value): void
    {
        (new ReflectionProperty($obj, $name))->setValue($obj, $value);
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function buildVolAnalysis(array $jobs = [], string $name = 'Test'): array
    {
        return ['name' => $name, 'jobs' => $jobs];
    }

    private function buildJob(int $id, string $source, string $target, array $chunks): array
    {
        return ['id' => $id, 'source' => $source, 'target' => $target, 'chunks' => $chunks];
    }


    public function testGetTotalPayableWordsReturnsCorrectSum(): void
    {
        $translated = self::createTranslatedStub();
        $this->setProperty($translated, 'jobList', [
            ['jid' => 100, 'jpassword' => 'abc'],
            ['jid' => 200, 'jpassword' => 'def'],
        ]);

        $vol = $this->buildVolAnalysis([
            $this->buildJob(100, 'en-US', 'it-IT', [['password' => 'abc', 'total_equivalent' => 150]]),
            $this->buildJob(200, 'en-US', 'fr-FR', [['password' => 'def', 'total_equivalent' => 250]]),
        ]);

        self::assertSame(400, self::invokeMethod($translated, 'getTotalPayableWords', [$vol]));
    }

    public function testGetTotalPayableWordsReturnsOneWhenZero(): void
    {
        $translated = self::createTranslatedStub();
        $this->setProperty($translated, 'jobList', [['jid' => 100, 'jpassword' => 'abc']]);

        $vol = $this->buildVolAnalysis([
            $this->buildJob(999, 'en-US', 'it-IT', [['password' => 'xyz', 'total_equivalent' => 500]]),
        ]);

        self::assertSame(1, self::invokeMethod($translated, 'getTotalPayableWords', [$vol]));
    }

    public function testGetTotalPayableWordsMultipleChunks(): void
    {
        $translated = self::createTranslatedStub();
        $this->setProperty($translated, 'jobList', [['jid' => 10, 'jpassword' => 'p1']]);

        $vol = $this->buildVolAnalysis([
            $this->buildJob(10, 'en', 'it', [
                ['password' => 'p1', 'total_equivalent' => 100],
                ['password' => 'p2', 'total_equivalent' => 200],
            ]),
        ]);

        self::assertSame(100, self::invokeMethod($translated, 'getTotalPayableWords', [$vol]));
    }

    public function testGetTotalPayableWordsCastsToInt(): void
    {
        $translated = self::createTranslatedStub();
        $this->setProperty($translated, 'jobList', [['jid' => 1, 'jpassword' => 'a']]);

        $vol = $this->buildVolAnalysis([
            $this->buildJob(1, 'en', 'it', [['password' => 'a', 'total_equivalent' => 99.7]]),
        ]);

        self::assertSame(99, self::invokeMethod($translated, 'getTotalPayableWords', [$vol]));
    }


    public function testGetLangPairsReturnsMatchingPair(): void
    {
        $translated = self::createTranslatedStub();
        $vol = $this->buildVolAnalysis([
            $this->buildJob(42, 'en-US', 'de-DE', [['password' => 'secret123']]),
        ]);

        self::assertSame(
            ['source' => 'en-US', 'target' => 'de-DE'],
            self::invokeMethod($translated, 'getLangPairs', [42, 'secret123', $vol])
        );
    }

    public function testGetLangPairsReturnsEmptyWhenNoMatch(): void
    {
        $translated = self::createTranslatedStub();
        $vol = $this->buildVolAnalysis([
            $this->buildJob(42, 'en-US', 'de-DE', [['password' => 'secret123']]),
        ]);

        self::assertSame([], self::invokeMethod($translated, 'getLangPairs', [99, 'wrong', $vol]));
    }

    public function testGetLangPairsAcceptsStringJid(): void
    {
        $translated = self::createTranslatedStub();
        $vol = $this->buildVolAnalysis([
            $this->buildJob(42, 'en-US', 'ja-JP', [['password' => 'pw']]),
        ]);

        self::assertSame(
            ['source' => 'en-US', 'target' => 'ja-JP'],
            self::invokeMethod($translated, 'getLangPairs', ['42', 'pw', $vol])
        );
    }

    public function testGetLangPairsMatchesPasswordNotJustId(): void
    {
        $translated = self::createTranslatedStub();
        $vol = $this->buildVolAnalysis([
            $this->buildJob(1, 'en', 'it', [['password' => 'correct']]),
        ]);

        self::assertSame([], self::invokeMethod($translated, 'getLangPairs', [1, 'wrong', $vol]));
    }


    public function testGetOutsourceConfirmUrlFormatsCorrectly(): void
    {
        $translated = self::createTranslatedStub();

        (new ReflectionProperty(Translated::class, 'OUTSOURCE_URL_CONFIRM'))
            ->setValue(null, 'https://example.com/confirm/%u/%s');

        $this->setProperty($translated, 'jobList', [
            ['jid' => 123, 'jpassword' => 'abc'],
            ['jid' => 456, 'jpassword' => 'def'],
        ]);

        $urls = $translated->getOutsourceConfirmUrl();

        self::assertCount(2, $urls);
        self::assertSame('https://example.com/confirm/123/abc', $urls[0]);
        self::assertSame('https://example.com/confirm/456/def', $urls[1]);
    }

    public function testGetOutsourceConfirmUrlEmptyJobList(): void
    {
        $translated = self::createTranslatedStub();

        (new ReflectionProperty(Translated::class, 'OUTSOURCE_URL_CONFIRM'))
            ->setValue(null, 'https://x.com/%u/%s');

        $this->setProperty($translated, 'jobList', []);

        self::assertSame([], $translated->getOutsourceConfirmUrl());
    }


    public function testSetFixedDeliveryReturnsSelf(): void
    {
        $translated = self::createTranslatedStub();
        $result = $translated->setFixedDelivery('1234567890000');

        self::assertSame($translated, $result);
        self::assertSame('1234567890000', (new ReflectionProperty($translated, 'fixedDelivery'))->getValue($translated));
    }

    public function testSetTypeOfServiceReturnsSelf(): void
    {
        $translated = self::createTranslatedStub();
        $result = $translated->setTypeOfService('premium');

        self::assertSame($translated, $result);
        self::assertSame('premium', (new ReflectionProperty($translated, 'typeOfService'))->getValue($translated));
    }


    public function testPrepareOutsourcedJobCartReturnsNullWhenNoLangPairs(): void
    {
        $translated = self::createTranslatedStub();
        $this->setProperty($translated, 'jobList', []);

        $result = self::invokeMethod($translated, '__prepareOutsourcedJobCart', [
            '999-pass-outsourced',
            $this->buildVolAnalysis(),
            'general',
            ['code' => 1, 'outsourced' => 1, 'price' => 10.0, 'delivery' => '2026-01-01', 'type_of_service' => 'professional', 'link_to_status' => 'https://x.com'],
        ]);

        self::assertNull($result);
    }

    public function testPrepareOutsourcedJobCartBuildsItem(): void
    {
        $translated = self::createTranslatedStub();
        $this->setProperty($translated, 'jobList', [['jid' => 50, 'jpassword' => 'pw']]);
        $this->setProperty($translated, 'currency', 'USD');
        $this->setProperty($translated, 'timezone', '1');

        $vol = $this->buildVolAnalysis([
            $this->buildJob(50, 'en-US', 'fr-FR', [['password' => 'pw', 'total_equivalent' => 300]]),
        ], 'MyProject');

        $apiResult = [
            'code'            => 1,
            'outsourced'      => 1,
            'price'           => 42.50,
            'delivery'        => '2026-06-01 12:00:00',
            'type_of_service' => 'premium',
            'link_to_status'  => 'https://status.example.com/123',
        ];

        $item = self::invokeMethod($translated, '__prepareOutsourcedJobCart', [
            '50-pw-outsourced', $vol, 'legal', $apiResult,
        ]);

        self::assertInstanceOf(ItemHTSQuoteJob::class, $item);
        self::assertSame('50-pw-outsourced', $item['id']);
        self::assertSame('MyProject', $item['project_name']);
        self::assertSame('en-US', $item['source']);
        self::assertSame('fr-FR', $item['target']);
        self::assertSame('legal', $item['subject']);
        self::assertSame('USD', $item['currency']);
        self::assertSame('1', $item['outsourced']);
        self::assertSame('premium', $item['typeOfService']);
        self::assertSame('42.5', $item['price']);
        self::assertSame('2026-06-01 12:00:00', $item['delivery']);
        self::assertSame('https://status.example.com/123', $item['link_to_status']);
    }


    public function testPrepareQuotedJobCartBuildsBasicItem(): void
    {
        $translated = self::createTranslatedStub();
        $this->setProperty($translated, 'jobList', [['jid' => 10, 'jpassword' => 'pp']]);
        $this->setProperty($translated, 'currency', 'EUR');
        $this->setProperty($translated, 'timezone', '2');
        $this->setProperty($translated, 'typeOfService', 'professional');

        $vol = $this->buildVolAnalysis([
            $this->buildJob(10, 'it-IT', 'en-US', [['password' => 'pp', 'total_equivalent' => 500]]),
        ], 'QuoteProject');

        $apiResult = [
            'code'                => 1,
            'quote_available'     => 0,
            'show_translator_data' => 0,
            'show_revisor_data'   => 0,
            'pid'                 => 99999,
            'showquote'           => 0,
            'translation'         => [],
            'revision'            => [],
        ];

        $item = self::invokeMethod($translated, '__prepareQuotedJobCart', [
            '10-pp-0', $vol, 'general', $apiResult,
        ]);

        self::assertInstanceOf(ItemHTSQuoteJob::class, $item);
        self::assertSame('10-pp-0', $item['id']);
        self::assertSame('QuoteProject', $item['project_name']);
        self::assertSame('it-IT', $item['source']);
        self::assertSame('en-US', $item['target']);
        self::assertSame('general', $item['subject']);
        self::assertSame('General', $item['subject_printable']);
        self::assertSame('EUR', $item['currency']);
        self::assertSame('0', $item['outsourced']);
        self::assertSame('0', $item['quote_available']);
        self::assertSame('professional', $item['typeOfService']);
    }

    public function testPrepareQuotedJobCartFillsTranslationData(): void
    {
        $translated = self::createTranslatedStub();
        $this->setProperty($translated, 'jobList', [['jid' => 10, 'jpassword' => 'pp']]);
        $this->setProperty($translated, 'currency', 'EUR');
        $this->setProperty($translated, 'timezone', '0');
        $this->setProperty($translated, 'typeOfService', 'professional');

        $vol = $this->buildVolAnalysis([
            $this->buildJob(10, 'en', 'it', [['password' => 'pp', 'total_equivalent' => 100]]),
        ]);

        $apiResult = [
            'code'                 => 1,
            'quote_available'      => 1,
            'show_translator_data' => 1,
            'show_revisor_data'    => 1,
            'pid'                  => 55555,
            'showquote'            => 1,
            'translation'          => [
                'price'                   => 25.0,
                'delivery'                => '2026-07-01 09:00:00',
                'translator_name'         => 'Mario Rossi',
                'translator_native_lang'  => 'it-IT',
                'translator_words_specific' => 5000,
                'translator_words_total'  => 80000,
                'translator_vote'         => 4.8,
                'translator_positive_feedbacks' => 95,
                'translator_total_feedbacks' => 100,
                'translator_experience_years' => 10,
                'translator_education'    => 'University',
                'chosen_subject'          => 'general',
                'translator_subjects'     => 'general,legal',
            ],
            'revision' => [
                'price'       => 5.0,
                'delivery'    => '2026-07-02 09:00:00',
                'revisor_vote' => 0.2,
            ],
        ];

        $item = self::invokeMethod($translated, '__prepareQuotedJobCart', [
            '10-pp-1234', $vol, 'general', $apiResult,
        ]);

        self::assertSame('25', $item['price']);
        self::assertSame('2026-07-01 09:00:00', $item['delivery']);
        self::assertSame('5', $item['r_price']);
        self::assertSame('55555', $item['quote_pid']);
        self::assertSame('1', $item['show_info']);
        self::assertSame('Mario Rossi', $item['t_name']);
        self::assertSame('it-IT', $item['t_native_lang']);
        self::assertSame('5000', $item['t_words_specific']);
        self::assertSame('4.8', $item['t_vote']);
        self::assertSame('0.2', $item['r_vote']);
        self::assertSame('1', $item['show_revisor_data']);
    }


    public function testAddCartElementToCartAddsAndDeletes(): void
    {
        $this->ensureSession();
        $translated = self::createTranslatedStub();

        $cart = Cart::getInstance('test_add_cart');
        $cart->emptyCart();

        $item = new ItemHTSQuoteJob();
        $item['id'] = '100-abc-0';
        $item['quantity'] = 1;
        $item['price'] = 20;

        self::invokeMethod($translated, '__addCartElementToCart', [$item, 'test_add_cart', false]);
        self::assertTrue($cart->itemExists('100-abc-0'));
    }

    public function testAddCartElementToCartDeletesOnPartialMatch(): void
    {
        $this->ensureSession();
        $translated = self::createTranslatedStub();

        $cart = Cart::getInstance('test_partial');
        $cart->emptyCart();

        $old = new ItemHTSQuoteJob();
        $old['id'] = '100-abc-111';
        $old['quantity'] = 1;
        $old['price'] = 10;
        $cart->addItem($old);

        self::assertTrue($cart->itemExists('100-abc-111'));

        $new = new ItemHTSQuoteJob();
        $new['id'] = '100-abc-outsourced';
        $new['quantity'] = 1;
        $new['price'] = 30;

        self::invokeMethod($translated, '__addCartElementToCart', [$new, 'test_partial', true]);

        self::assertFalse($cart->itemExists('100-abc-111'));
        self::assertTrue($cart->itemExists('100-abc-outsourced'));
    }

    public function testAddCartElementToCartThrowsOnInvalidIdFormat(): void
    {
        $this->ensureSession();
        $translated = self::createTranslatedStub();

        $item = new ItemHTSQuoteJob();
        $item['id'] = 'nohyphen';
        $item['quantity'] = 1;
        $item['price'] = 10;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid cart element ID format');

        self::invokeMethod($translated, '__addCartElementToCart', [$item, 'outsource_to_external_cache', true]);
    }

    public function testAddCartElementPopulatesBothCartsAndQuoteResult(): void
    {
        $this->ensureSession();
        $translated = self::createTranslatedStub();
        $this->setProperty($translated, '_quote_result', []);

        Cart::getInstance('outsource_to_external')->emptyCart();
        Cart::getInstance('outsource_to_external_cache')->emptyCart();

        $item = new ItemHTSQuoteJob();
        $item['id'] = '77-zz-0';
        $item['quantity'] = 1;
        $item['price'] = 15;

        self::invokeMethod($translated, '__addCartElement', [$item, false]);

        self::assertTrue(Cart::getInstance('outsource_to_external')->itemExists('77-zz-0'));
        self::assertTrue(Cart::getInstance('outsource_to_external_cache')->itemExists('77-zz-0'));

        $quoteResult = (new ReflectionProperty($translated, '_quote_result'))->getValue($translated);
        self::assertCount(1, $quoteResult);
        self::assertIsArray($quoteResult[0]);
        self::assertInstanceOf(ItemHTSQuoteJob::class, $quoteResult[0][0]);
    }


    public function testUpdateCartElementsThrowsWhenCartItemIsNull(): void
    {
        $this->ensureSession();
        $translated = self::createTranslatedStub();
        Cart::getInstance('outsource_to_external_cache')->emptyCart();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cart item not found');

        self::invokeMethod($translated, '__updateCartElements', ['nonexistent-id', 'EUR', '0']);
    }

    public function testUpdateCartElementsUpdatesCurrencyAndTimezone(): void
    {
        $this->ensureSession();
        $translated = self::createTranslatedStub();
        $this->setProperty($translated, '_quote_result', []);

        Cart::getInstance('outsource_to_external')->emptyCart();
        Cart::getInstance('outsource_to_external_cache')->emptyCart();

        $item = new ItemHTSQuoteJob();
        $item['id'] = '55-xx-0';
        $item['quantity'] = 1;
        $item['price'] = 10;
        $item['currency'] = 'EUR';
        $item['timezone'] = '0';
        $item['typeOfService'] = 'professional';
        Cart::getInstance('outsource_to_external_cache')->addItem($item);

        self::invokeMethod($translated, '__updateCartElements', ['55-xx-0', 'USD', '5', 'premium']);

        $updated = Cart::getInstance('outsource_to_external_cache')->getItem('55-xx-0');
        self::assertNotNull($updated);
        self::assertSame('USD', $updated['currency']);
        self::assertSame('5', $updated['timezone']);
        self::assertSame('premium', $updated['typeOfService']);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testConstructorSetsUrlsAndCurlOptions(): void
    {
        \Utils\Registry\AppConfig::$HTTPHOST = 'https://test.matecat.com';
        \Utils\Registry\AppConfig::$BASEURL = '/';
        \Utils\Registry\AppConfig::$BUILD_NUMBER = '42';

        $translated = new Translated();

        self::assertSame('https://test.matecat.com/webhooks/outsource/success', $translated->getOutsourceLoginUrlOk());
        self::assertSame('https://test.matecat.com/webhooks/outsource/failure', $translated->getOutsourceLoginUrlKo());

        $curlOpts = (new ReflectionProperty($translated, '_curlOptions'))->getValue($translated);
        self::assertSame(true, $curlOpts[CURLOPT_RETURNTRANSFER]);
        self::assertSame(10, $curlOpts[CURLOPT_TIMEOUT]);
        self::assertSame(5, $curlOpts[CURLOPT_CONNECTTIMEOUT]);
        self::assertStringContainsString('Matecat-Cattool/v42', $curlOpts[CURLOPT_USERAGENT]);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testPerformQuoteThrowsWhenFeaturesNull(): void
    {
        \Utils\Registry\AppConfig::$HTTPHOST = 'https://test.matecat.com';
        \Utils\Registry\AppConfig::$BASEURL = '/';
        \Utils\Registry\AppConfig::$BUILD_NUMBER = '1';

        $translated = new Translated();
        $this->setProperty($translated, 'jobList', [['jid' => 1, 'jpassword' => 'p']]);
        $this->setProperty($translated, 'pid', 999);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FeatureSet is required');

        $translated->performQuote();
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testProcessOutsourcedJobsWithEmptyJobList(): void
    {
        \Utils\Registry\AppConfig::$HTTPHOST = 'https://test.matecat.com';
        \Utils\Registry\AppConfig::$BASEURL = '/';
        \Utils\Registry\AppConfig::$BUILD_NUMBER = '1';

        $translated = new Translated();
        $this->setProperty($translated, 'jobList', []);
        $this->setProperty($translated, 'currency', 'EUR');
        $this->setProperty($translated, 'timezone', '0');

        self::invokeMethod($translated, '__processOutsourcedJobs', ['general', $this->buildVolAnalysis()]);

        self::assertTrue(true);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testProcessNormalJobsWithEmptyJobList(): void
    {
        \Utils\Registry\AppConfig::$HTTPHOST = 'https://test.matecat.com';
        \Utils\Registry\AppConfig::$BASEURL = '/';
        \Utils\Registry\AppConfig::$BUILD_NUMBER = '1';

        $translated = new Translated();
        $this->setProperty($translated, 'jobList', []);
        $this->setProperty($translated, 'fixedDelivery', '0');

        self::invokeMethod($translated, '__processNormalJobs', ['general', $this->buildVolAnalysis()]);

        self::assertTrue(true);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testProcessOutsourcedJobsSkipsCachedItems(): void
    {
        \Utils\Registry\AppConfig::$HTTPHOST = 'https://test.matecat.com';
        \Utils\Registry\AppConfig::$BASEURL = '/';
        \Utils\Registry\AppConfig::$BUILD_NUMBER = '1';

        $translated = new Translated();
        $this->setProperty($translated, 'currency', 'EUR');
        $this->setProperty($translated, 'timezone', '0');
        $this->setProperty($translated, 'jobList', [['jid' => 10, 'jpassword' => 'pw']]);
        $this->setProperty($translated, '_quote_result', []);

        $cachedItem = new ItemHTSQuoteJob();
        $cachedItem['id'] = '10-pw-outsourced';
        $cachedItem['quantity'] = 1;
        $cachedItem['price'] = 50;
        $cachedItem['currency'] = 'EUR';
        $cachedItem['timezone'] = '0';
        $cachedItem['typeOfService'] = 'professional';
        Cart::getInstance('outsource_to_external_cache')->addItem($cachedItem);

        self::invokeMethod($translated, '__processOutsourcedJobs', [
            'general',
            $this->buildVolAnalysis([
                $this->buildJob(10, 'en', 'it', [['password' => 'pw', 'total_equivalent' => 100]]),
            ]),
        ]);

        self::assertTrue(Cart::getInstance('outsource_to_external_cache')->itemExists('10-pw-outsourced'));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testProcessNormalJobsSkipsOutsourcedAndCachedItems(): void
    {
        \Utils\Registry\AppConfig::$HTTPHOST = 'https://test.matecat.com';
        \Utils\Registry\AppConfig::$BASEURL = '/';
        \Utils\Registry\AppConfig::$BUILD_NUMBER = '1';

        $translated = new Translated();
        $this->setProperty($translated, 'fixedDelivery', '0');
        $this->setProperty($translated, 'typeOfService', 'professional');
        $this->setProperty($translated, 'currency', 'EUR');
        $this->setProperty($translated, 'timezone', '0');
        $this->setProperty($translated, 'jobList', [['jid' => 20, 'jpassword' => 'pw2']]);
        $this->setProperty($translated, '_quote_result', []);

        $cachedItem = new ItemHTSQuoteJob();
        $cachedItem['id'] = '20-pw2-outsourced';
        $cachedItem['quantity'] = 1;
        $cachedItem['price'] = 30;
        $cachedItem['currency'] = 'EUR';
        $cachedItem['timezone'] = '0';
        $cachedItem['typeOfService'] = 'professional';
        Cart::getInstance('outsource_to_external_cache')->addItem($cachedItem);

        self::invokeMethod($translated, '__processNormalJobs', [
            'general',
            $this->buildVolAnalysis([
                $this->buildJob(20, 'en', 'fr', [['password' => 'pw2', 'total_equivalent' => 200]]),
            ]),
        ]);

        self::assertTrue(Cart::getInstance('outsource_to_external_cache')->itemExists('20-pw2-outsourced'));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testProcessNormalJobsSkipsQuoteCachedItems(): void
    {
        \Utils\Registry\AppConfig::$HTTPHOST = 'https://test.matecat.com';
        \Utils\Registry\AppConfig::$BASEURL = '/';
        \Utils\Registry\AppConfig::$BUILD_NUMBER = '1';

        $translated = new Translated();
        $this->setProperty($translated, 'fixedDelivery', '555');
        $this->setProperty($translated, 'typeOfService', 'professional');
        $this->setProperty($translated, 'currency', 'GBP');
        $this->setProperty($translated, 'timezone', '3');
        $this->setProperty($translated, 'jobList', [['jid' => 30, 'jpassword' => 'pw3']]);
        $this->setProperty($translated, '_quote_result', []);

        $cachedQuote = new ItemHTSQuoteJob();
        $cachedQuote['id'] = '30-pw3-555';
        $cachedQuote['quantity'] = 1;
        $cachedQuote['price'] = 25;
        $cachedQuote['currency'] = 'EUR';
        $cachedQuote['timezone'] = '0';
        $cachedQuote['typeOfService'] = 'professional';
        Cart::getInstance('outsource_to_external_cache')->addItem($cachedQuote);

        self::invokeMethod($translated, '__processNormalJobs', [
            'general',
            $this->buildVolAnalysis([
                $this->buildJob(30, 'en', 'de', [['password' => 'pw3', 'total_equivalent' => 150]]),
            ]),
        ]);

        $updated = Cart::getInstance('outsource_to_external_cache')->getItem('30-pw3-555');
        self::assertNotNull($updated);
        self::assertSame('GBP', $updated['currency']);
        self::assertSame('3', $updated['timezone']);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testProcessNormalJobsSkipsWhenNoLangPairs(): void
    {
        \Utils\Registry\AppConfig::$HTTPHOST = 'https://test.matecat.com';
        \Utils\Registry\AppConfig::$BASEURL = '/';
        \Utils\Registry\AppConfig::$BUILD_NUMBER = '1';

        $translated = new Translated();
        $this->setProperty($translated, 'fixedDelivery', '0');
        $this->setProperty($translated, 'typeOfService', 'professional');
        $this->setProperty($translated, 'currency', 'EUR');
        $this->setProperty($translated, 'timezone', '0');
        $this->setProperty($translated, 'pid', 1);
        $this->setProperty($translated, 'ppassword', 'pp');
        $this->setProperty($translated, 'jobList', [['jid' => 99, 'jpassword' => 'nomatch']]);

        self::invokeMethod($translated, '__processNormalJobs', [
            'general',
            $this->buildVolAnalysis([
                $this->buildJob(1, 'en', 'fr', [['password' => 'other']]),
            ]),
        ]);

        self::assertTrue(true);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testProcessOutsourcedJobsSkipsWhenNoLangPairsForUncachedJob(): void
    {
        \Utils\Registry\AppConfig::$HTTPHOST = 'https://test.matecat.com';
        \Utils\Registry\AppConfig::$BASEURL = '/';
        \Utils\Registry\AppConfig::$BUILD_NUMBER = '1';

        $translated = new Translated();
        $this->setProperty($translated, 'currency', 'EUR');
        $this->setProperty($translated, 'timezone', '0');
        $this->setProperty($translated, 'pid', 1);
        $this->setProperty($translated, 'ppassword', 'pp');
        $this->setProperty($translated, 'jobList', [['jid' => 88, 'jpassword' => 'pw']]);

        self::invokeMethod($translated, '__processOutsourcedJobs', [
            'general',
            $this->buildVolAnalysis([
                $this->buildJob(88, 'en', 'it', [['password' => 'pw', 'total_equivalent' => 50]]),
            ]),
        ]);

        self::assertFalse(Cart::getInstance('outsource_to_external_cache')->itemExists('88-pw-outsourced'));
    }
}
