<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 28/05/26
 * Time: 16:34
 *
 */

namespace Model\ConnectedServices\Oauth\LinkedIn;

use League\OAuth2\Client\Provider\LinkedIn;
use League\OAuth2\Client\Provider\LinkedInResourceOwner;
use League\OAuth2\Client\Token\AccessToken;

final class LinkedinFinal extends LinkedIn
{

    /**
     * Get provider url to fetch user details
     * Standard LinkedIn API call to get the user info, as the library crash with not enough permissions
     * when calling https://api.linkedin.com/v2/me
     * @param AccessToken $token
     *
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token): string
    {
        return 'https://api.linkedin.com/v2/userinfo';
    }

    public function getResourceOwner(AccessToken $token): LinkedInResourceOwner
    {
        return new LinkedInResourceOwner($this->fetchResourceOwnerDetails($token));
    }

}