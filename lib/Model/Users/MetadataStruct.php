<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/12/2016
 * Time: 22:51
 */

namespace Users;

use DataAccess_IDaoStruct;
use JsonSerializable;

class MetadataStruct extends \DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct, JsonSerializable
{
    public $id;
    public $uid;
    public $key;
    public $value;

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'id' => (int)$this->id,
            'uid' => (int)$this->uid,
            'key' => (string)$this->key,
            'value' => $this->getValue()
        ];
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        // in case of numeric value, return a integer
        if(is_numeric($this->value)){
            return (int)$this->value;
        }

        // in case of serialized data, return an object
        if (@unserialize($this->value) !== false) {
            return (object)unserialize($this->value);
        }

        // return a string
        return $this->value;
    }
}