<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 12:56
 */

namespace API\V2\Json;


use TmKeyManagement_ClientTmKeyStruct;

class JobClientKeys {

    /**
     * @var TmKeyManagement_ClientTmKeyStruct[]
     */
    protected $data = [];

    /**
     * Project constructor.
     *
     * @param TmKeyManagement_ClientTmKeyStruct[] $data
     */
    public function __construct( array $data = [] ) {
        $this->data      = $data;
    }

    public static function renderItem( TmKeyManagement_ClientTmKeyStruct $keyStruct ) {

        return [
                "key"  => $keyStruct->key,
                "r"    => ( $keyStruct->r ),
                "w"    => ( $keyStruct->w ),
                "name" => $keyStruct->name
        ];

    }

    /**
     * @return array
     */
    public function render() {
        $out = [];
        foreach ( $this->data as $keyStruct ) {
            $out[] = $this->renderItem( $keyStruct );
        }
        return $out;
    }

}