<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 29/09/14
 * Time: 18.07
 */

namespace Model\TmKeyManagement;

use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;
use Utils\TmKeyManagement\TmKeyStruct;

/**
 * Class MemoryKeyStruct<br>
 * This class represents a row in the table memory_keys.
 */
class MemoryKeyStruct extends AbstractDaoObjectStruct implements IDaoStruct
{

    /**
     * @var integer The user's ID
     */
    public int $uid;

    /**
     * @var TmKeyStruct|null
     */
    public ?TmKeyStruct $tm_key = null;

    /**
     * Converts the current object into an associative array
     *
     * @param             $mask  array|null
     * @param object|null $class this is not used but is required by the interface
     *
     * @return array
     * @see AbstractDaoObjectStruct::toArray
     */
    public function toArray(array $mask = null, object $class = null): array
    {
        $result = (array)$this;

        if ($this->tm_key instanceof TmKeyStruct) {
            /**
             * we use toArray() because TmKeyStruct implements JsonSerializable were we control the output result by filtering the fields we want to return.
             * @see TmKeyStruct::$complete_format
             */
            $result['tm_key'] = $this->tm_key->toArray();
        }

        return $result;
    }

}
