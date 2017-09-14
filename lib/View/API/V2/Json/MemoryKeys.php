<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 12:56
 */

namespace API\V2\Json;


use TmKeyManagement_MemoryKeyStruct;

class MemoryKeys {

    /**
     * @var TmKeyManagement_MemoryKeyStruct[]
     */
    protected $data = [];

    /**
     * Project constructor.
     *
     * @param TmKeyManagement_MemoryKeyStruct[] $data
     */
    public function __construct( array $data = [] ) {
        $this->data      = $data;
    }

    public static function renderItem( TmKeyManagement_MemoryKeyStruct $keyStruct ) {


        return [
                'key'  => $keyStruct->tm_key->key,
                'name' => $keyStruct->tm_key->name
        ];

    }

    public function render() {
        $out = [];
        foreach ( $this->data as $keyStruct ) {

            $keyType = 'private_keys';
            if ( $keyStruct->tm_key->isShared() ) {
                $keyType = 'shared_keys';
            }

            $out[ $keyType ][] = $this->renderItem( $keyStruct );

        }

        return $out;
    }

}