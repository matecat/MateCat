<?php

namespace Model\Users;

use stdClass;
use Stringable;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 11/10/16
 * Time: 18.45
 *
 */
class ClientUserFacade extends stdClass implements Stringable
{

    public int $uid;
    public string $email;
    public string $first_name;
    public string $last_name;

    /**
     * ClientUserFacade constructor.
     *
     * @param UserStruct $userStruct
     */
    public function __construct(UserStruct $userStruct)
    {
        foreach ($userStruct as $property => $value) {
            if (property_exists($this, $property)) {
                $this->$property = $value;
            }
        }
    }

    public function __toString(): string
    {
        return json_encode($this);
    }

}