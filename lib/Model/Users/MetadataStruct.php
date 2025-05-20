<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/12/2016
 * Time: 22:51
 */

namespace Users;

use \DataAccess\AbstractDaoObjectStruct;
use \DataAccess\IDaoStruct;
use JsonSerializable;

class MetadataStruct extends AbstractDaoObjectStruct implements IDaoStruct, JsonSerializable {
    public string $id;
    public string $uid;
    public string $key;
    public        $value;

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {
        return [
                'id'    => (int)$this->id,
                'uid'   => (int)$this->uid,
                'key'   => (string)$this->key,
                'value' => $this->getValue()
        ];
    }

    /**
     * @return mixed
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