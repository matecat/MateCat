<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/12/2016
 * Time: 22:51
 */

namespace Model\Users;

use JsonSerializable;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;

class MetadataStruct extends AbstractDaoObjectStruct implements IDaoStruct, JsonSerializable {
    public string $id;
    public string $uid;
    public string $key;

    /**
     * @var int|object|string
     */
    public        $value;

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array {
        return [
                'id'    => (int)$this->id,
                'uid'   => (int)$this->uid,
                'key'   => $this->key,
                'value' => $this->getValue()
        ];
    }

    /**
     * @return int|object|string
     */
    public function getValue() {
        // in case of numeric value, return a integer
        if ( is_numeric( $this->value ) ) {
            return (int)$this->value;
        }

        // in case of serialized data, return an object
        if ( ( @unserialize( $this->value ) ?? false ) !== false ) {
            return (object)unserialize( $this->value );
        }

        // return a string
        return $this->value;
    }
}