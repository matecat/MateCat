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
use Utils\Tools\Utils;

class ConnectedService {

    /**
     * @var ConnectedServiceStruct[]
     */
    protected array $data;

    public function __construct( $data ) {
        $this->data = $data;
    }

    /**
     * @throws EnvironmentIsBrokenException
     */
    public function render(): array {
        $out = [];
        if ( !empty( $this->data ) ) {
            foreach ( $this->data as $v ) {
                $out[] = $this->renderItem( $v );
            }
        }

        return $out;
    }

    /**
     * @throws EnvironmentIsBrokenException
     * @throws Exception
     */
    public function renderItem( ConnectedServiceStruct $item ): array {
        /*
         * @var $item ConnectedServiceStruct
         */

        return [
                'id'                 => (int)$item->id,
                'uid'                => (int)$item->uid,
                'service'            => $item->service,
                'email'              => $item->email,
                'name'               => $item->name,
                'oauth_access_token' => $item->getDecryptedOauthAccessToken(),
                'created_at'         => Utils::api_timestamp( $item->created_at ),
                'updated_at'         => Utils::api_timestamp( $item->updated_at ),
                'disabled_at'        => Utils::api_timestamp( $item->disabled_at ),
                'expired_at'         => Utils::api_timestamp( $item->expired_at ),
                'is_default'         => !!$item->is_default,
        ];
    }

}