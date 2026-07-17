<?php


namespace Model\ConnectedServices\GDrive;

use Google_Client;
use Exception;

use function is_array;
use function is_string;

class GDriveTokenHandler
{

    /**
     * This function returns a new token if the previous is expired.
     * If not expired, false is returned.
     *
     * @param Google_Client $client
     * @param string|array<string, mixed> $raw_token
     *
     * @return false|string
     * @throws Exception
     */
    public static function getNewToken(Google_Client $client, string|array $raw_token): false|string
    {
        $client->setAccessToken($raw_token);

        $json_token = is_string($raw_token) ? json_decode($raw_token, true) : $raw_token;
        $refresh_token = $json_token['refresh_token'];

        if ($client->isAccessTokenExpired()) {
            $grants = $client->refreshToken($refresh_token);

            if (isset($grants['error'])) {
                throw new Exception($grants['error_description']);
            }

            $access_token = $client->getAccessToken();

            //
            // 2019-01-02
            // -------------------------
            // Google Api V3 return $access_token as an array, so we need to encode it to JSON string
            //
            if (is_array($access_token)) {
                $access_token = json_encode($access_token, JSON_UNESCAPED_SLASHES);
            }

            return $access_token;
        }

        return false;
    }

    /**
     * Enforce token to be passed around as json_string, to favor encryption and storage.
     * Prevent slash escape, see: http://stackoverflow.com/a/14419483/1297909
     *
     * @param string|array<string, mixed> $token
     *
     * @return string
     */
    public static function accessTokenToJsonString(string|array $token): string
    {
        if (!is_array($token)) {
            $token = json_decode($token);
        }

        return json_encode($token, JSON_UNESCAPED_SLASHES) ?: '';
    }
}
