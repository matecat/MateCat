<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/11/2016
 * Time: 11:50
 */

namespace View\API\App\Json;


use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Exception;
use Model\ConnectedServices\ConnectedServiceStruct;
use TypeError;
use Utils\Tools\Utils;

class ConnectedService
{

    /**
     * @var ConnectedServiceStruct[]
     */
    protected array $data;

    /**
     * @param ConnectedServiceStruct[] $data
     * @throws TypeError
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws EnvironmentIsBrokenException
     * @throws Exception
     * @throws TypeError
     */
    public function render(): array
    {
        $out = [];
        if (!empty($this->data)) {
            foreach ($this->data as $v) {
                $out[] = $this->renderItem($v);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     * @throws EnvironmentIsBrokenException
     * @throws Exception
     * @throws TypeError
     */
    public function renderItem(ConnectedServiceStruct $item): array
    {
        /*
         * @var $item ConnectedServiceStruct
         */

        return [
            'id' => (int)$item->id,
            'uid' => $item->uid,
            'service' => $item->service,
            'email' => $item->email,
            'name' => $item->name,
            'oauth_access_token' => $this->clientVisibleToken($item),
            'created_at' => Utils::api_timestamp($item->created_at),
            'updated_at' => Utils::api_timestamp($item->updated_at),
            'disabled_at' => Utils::api_timestamp($item->disabled_at),
            'expired_at' => Utils::api_timestamp($item->expired_at),
            'is_default' => !!$item->is_default,
        ];
    }

    /**
     * The part of the stored OAuth token the browser is allowed to see: the access token, and nothing
     * else.
     *
     * The stored value is the provider's whole token response — `access_token`, `refresh_token`,
     * `id_token`, `scope`, `expires_in`. It is encrypted at rest precisely because it is credential
     * material, and returning it verbatim undid that protection on every profile read: a refresh token
     * grants offline access to the user's Drive and, unlike the access token's a-few-minutes lifetime,
     * it does not expire on its own. Anything that can read a response body — an XSS, a browser
     * extension, an intermediary that logs — got a long-lived credential.
     *
     * Only `access_token` has a client-side consumer: the Google Picker runs in the browser and needs
     * it to call Drive (`useGDrivePicker.js`, which JSON-parses this field and reads `.access_token`).
     * The shape is preserved as a JSON string for that reason — this is a narrowing, not a contract
     * change.
     *
     * Returns null when there is no stored token, and preserves an unparseable one as null rather than
     * passing it through: if the payload is not the JSON object we expect, the safe reading is that it
     * holds nothing the client may have.
     *
     * @throws EnvironmentIsBrokenException
     * @throws Exception
     * @throws TypeError
     */
    private function clientVisibleToken(ConnectedServiceStruct $item): ?string
    {
        $stored = $item->getDecryptedOauthAccessToken();

        if (empty($stored)) {
            return null;
        }

        $decoded = json_decode($stored, true);

        if (!is_array($decoded) || !isset($decoded['access_token']) || !is_string($decoded['access_token'])) {
            return null;
        }

        return json_encode(['access_token' => $decoded['access_token']]) ?: null;
    }

}