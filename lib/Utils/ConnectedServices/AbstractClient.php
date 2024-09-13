<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 13/09/24
 * Time: 11:58
 *
 */

namespace ConnectedServices;

abstract class AbstractClient implements ConnectedServiceInterface {

    const PROVIDER_NAME = '';

    protected ?string $redirectUrl = null;

    public function __construct( ?string $redirectUrl = null ) {
        $this->redirectUrl = $redirectUrl;
    }

    /**
     * [SECURITY]
     * This method is meant to get a valid Oauth client without a user to generate a valid authentication url without passing to the client a CSRF token.
     * Must be user only to generate a valid Oauth url and a valid login sequence.
     *
     * @param string|null $redirectUrl
     *
     * @return mixed
     */
    public abstract static function getClient( ?string $redirectUrl = null );

}