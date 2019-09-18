<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/11/2016
 * Time: 11:50
 */

namespace API\App\Json;


use ConnectedServices\ConnectedServiceStruct;
use Utils;

class ConnectedService {

    /**
     * @var ConnectedServiceStruct[]
     */
    protected $data ;

    public function __construct( $data )
    {
        $this->data = $data ;
    }

    public function render() {
        $out = [] ;
        if( !empty( $this->data ) ) {
            foreach ( $this->data as $k => $v ) {
                $out[] = $this->renderItem( $v );
            }
        }
        return $out ;
    }

    public function renderItem(ConnectedServiceStruct $item) {
        /*
         * @var $item ConnectedServiceStruct
         */

        return array(
            'id' => (int) $item->id,
            'uid' => (int) $item->uid,
            'service' => $item->service,
            'email' => $item->email,
            'name' => $item->name,
            'oauth_access_token' => $item->getDecryptedOauthAccessToken(),
            'created_at' => Utils::api_timestamp( $item->created_at ),
            'updated_at' => Utils::api_timestamp( $item->updated_at ),
            'disabled_at' => Utils::api_timestamp( $item->disabled_at ),
            'expired_at' => Utils::api_timestamp( $item->expired_at ),
            'is_default' => !!$item->is_default,
        );
    }

}