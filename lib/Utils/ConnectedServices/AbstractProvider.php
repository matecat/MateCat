<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 13/09/24
 * Time: 11:58
 *
 */

namespace ConnectedServices;

abstract class AbstractProvider implements ProviderInterface {

    const PROVIDER_NAME = '';

    protected ?string $redirectUrl = null;

    public function __construct( ?string $redirectUrl = null ) {
        $this->redirectUrl = $redirectUrl;
    }

}