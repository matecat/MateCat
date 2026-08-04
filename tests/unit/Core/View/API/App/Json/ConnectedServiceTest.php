<?php

namespace Matecat\Core\View\API\App\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\ConnectedServiceStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\App\Json\ConnectedService;

#[CoversClass(ConnectedService::class)]
class ConnectedServiceTest extends AbstractTest
{
    private function makeStruct(int $id = 1): ConnectedServiceStruct
    {
        $struct                    = new ConnectedServiceStruct();
        $struct->id                = $id;
        $struct->uid               = 42;
        $struct->service           = 'google';
        $struct->email             = 'test@example.com';
        $struct->name              = 'Test User';
        $struct->oauth_access_token = null;
        $struct->created_at        = '2024-01-01 00:00:00';
        $struct->updated_at        = null;
        $struct->disabled_at       = null;
        $struct->expired_at        = null;
        $struct->is_default        = 1;

        return $struct;
    }

    public function testConstructorAcceptsEmptyArray(): void
    {
        $view = new ConnectedService([]);
        $this->assertInstanceOf(ConnectedService::class, $view);
    }

    public function testRenderEmptyDataReturnsEmptyArray(): void
    {
        $view   = new ConnectedService([]);
        $result = $view->render();

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testRenderItemReturnsExpectedKeys(): void
    {
        $struct = $this->makeStruct(5);
        $view   = new ConnectedService([$struct]);
        $result = $view->renderItem($struct);

        $this->assertSame(5, $result['id']);
        $this->assertSame(42, $result['uid']);
        $this->assertSame('google', $result['service']);
        $this->assertSame('test@example.com', $result['email']);
        $this->assertSame('Test User', $result['name']);
        $this->assertArrayHasKey('oauth_access_token', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('updated_at', $result);
        $this->assertArrayHasKey('disabled_at', $result);
        $this->assertArrayHasKey('expired_at', $result);
        $this->assertIsBool($result['is_default']);
        $this->assertTrue($result['is_default']);
    }

    public function testRenderReturnsOneItemPerStruct(): void
    {
        $s1   = $this->makeStruct(1);
        $s2   = $this->makeStruct(2);
        $view = new ConnectedService([$s1, $s2]);

        $result = $view->render();

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame(2, $result[1]['id']);
    }

    public function testRenderItemCastsIdToInt(): void
    {
        $struct     = $this->makeStruct();
        $struct->id = '7';
        $view       = new ConnectedService([$struct]);
        $result     = $view->renderItem($struct);

        $this->assertSame(7, $result['id']);
    }

    public function testIsDefaultFalseWhenZero(): void
    {
        $struct             = $this->makeStruct();
        $struct->is_default = 0;
        $view               = new ConnectedService([$struct]);
        $result             = $view->renderItem($struct);

        $this->assertFalse($result['is_default']);
    }

    /**
     * The provider returns one blob holding several credentials; only one of them has a client-side
     * consumer. This pins the narrowing, and it is the test that fails if the view goes back to
     * returning the stored value verbatim.
     */
    public function testTheRefreshAndIdTokensAreNeverSentToTheClient(): void
    {
        $struct = $this->makeStruct();
        $struct->setEncryptedAccessToken((string)json_encode([
            'access_token'  => 'ya29.the-short-lived-one',
            'expires_in'    => 3599,
            'refresh_token' => '1//the-long-lived-one',
            'scope'         => 'https://www.googleapis.com/auth/drive',
            'token_type'    => 'Bearer',
            'id_token'      => 'eyJ.identity.assertion',
        ]));

        $rendered = (new ConnectedService([]))->renderItem($struct);

        // Assert against the serialised field, because that is literally what reaches the browser.
        $this->assertStringNotContainsString('refresh_token', (string)$rendered['oauth_access_token']);
        $this->assertStringNotContainsString('1//the-long-lived-one', (string)$rendered['oauth_access_token']);
        $this->assertStringNotContainsString('id_token', (string)$rendered['oauth_access_token']);
        $this->assertStringNotContainsString('eyJ.identity.assertion', (string)$rendered['oauth_access_token']);
    }

    /**
     * The Picker parses this field and reads .access_token, so the narrowing must not break it.
     */
    public function testTheAccessTokenStillReachesThePickerInTheShapeItParses(): void
    {
        $struct = $this->makeStruct();
        $struct->setEncryptedAccessToken((string)json_encode([
            'access_token'  => 'ya29.the-short-lived-one',
            'refresh_token' => '1//the-long-lived-one',
        ]));

        $rendered = (new ConnectedService([]))->renderItem($struct);
        $decoded  = json_decode((string)$rendered['oauth_access_token'], true);

        $this->assertIsArray($decoded);
        $this->assertSame('ya29.the-short-lived-one', $decoded['access_token']);
        $this->assertSame(['access_token'], array_keys($decoded));
    }

    public function testAServiceWithNoStoredTokenRendersNull(): void
    {
        $this->assertNull((new ConnectedService([]))->renderItem($this->makeStruct())['oauth_access_token']);
    }

    /**
     * A stored value that is not the JSON object we expect is dropped rather than forwarded: if we
     * cannot tell which part of it is the access token, none of it is safe to hand over.
     */
    public function testAnUnparseableStoredTokenIsDroppedRatherThanForwarded(): void
    {
        $struct = $this->makeStruct();
        $struct->setEncryptedAccessToken('not-json-at-all');

        $this->assertNull((new ConnectedService([]))->renderItem($struct)['oauth_access_token']);
    }
}
