<?php

namespace ConnectedServices\LinkedIn;

use Exception;
use Utils;

class LinkedInClient
{
    /**
     * @return string
     */
    public static function getAuthorizationUrl() {

        $options = [
            'state' => Utils::randomString(20),
            'scope' => [
                'email',
                'profile',
                'openid',
            ]
        ];
        $linkedInClient = LinkedInClientFactory::create();

        return $linkedInClient->getAuthorizationUrl($options);
    }

    /**
     * @param $code
     * @return \League\OAuth2\Client\Provider\ResourceOwnerInterface
     */
    public static function getResourceOwner($code){

        $linkedInClient = LinkedInClientFactory::create();

        try {
            $token = $linkedInClient->getAccessToken('authorization_code', [
                'code' => $code
            ]);

            return $linkedInClient->getResourceOwner($token);

        } catch (Exception $e) {

            // Failed to get user details
            exit('Oh dear...');
        }
    }
}

//https://dev.matecat.com/?code=AQSG1jBQC1BzpuvTypL87NN2VVSSEWTpuPUF3ZrMspEl7GYYMJuREvc4k5bAVLyYtaAMEfP2Ru-Bzw--BZRuCzQo2MrDemEYrIbasJGpmQH8DiYYOSpy-VnxQF1IOEFFAx2zgTDAIExFCwgGfcdhRGuhD3bHNEdr0mWiOsUK-9BBDiX7H_7PrxmmAzkzslH4RYXDy7dbaLmfq_HZ8YE&state=5551e201ee09be84c2d1

//config.linkedInAuthUrl
