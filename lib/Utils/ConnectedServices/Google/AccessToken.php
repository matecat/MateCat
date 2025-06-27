<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 04/10/24
 * Time: 18:41
 *
 */

namespace Utils\ConnectedServices\Google;

class AccessToken extends \League\OAuth2\Client\Token\AccessToken {

    protected array $originalValues = [];

    public function __construct( array $options = [] ) {
        $this->originalValues = $options;
        parent::__construct( $options );
    }

    /**
     * @return array
     */
    public function __toArray(): array {
        return [
                'access_token'  => $this->accessToken,
                'expires_in'    => $this->originalValues[ 'expires_in' ],
                'refresh_token' => $this->refreshToken,
                'scope'         => $this->originalValues[ 'scope' ],
                'token_type'    => $this->originalValues[ 'token_type' ],
                'id_token'      => $this->originalValues[ 'id_token' ],
                'created'       => $this->originalValues[ 'created' ]
        ];

    }

}