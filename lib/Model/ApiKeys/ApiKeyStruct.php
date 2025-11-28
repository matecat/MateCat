<?php

namespace Model\ApiKeys;

use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;

class ApiKeyStruct extends AbstractDaoObjectStruct implements IDaoStruct
{

    public ?int   $id = null;
    public int    $uid;
    public string $api_key;
    public string $api_secret;
    public string $create_date;
    public string $last_update;
    public bool   $enabled;

    public function validSecret($secret): bool
    {
        return $this->api_secret == $secret;
    }

    /**
     * @throws ReflectionException
     */
    public function getUser(): ?UserStruct
    {
        $dao = new UserDao(Database::obtain());
        $dao->setCacheTTL(3600);

        return $dao->getByUid($this->uid);
    }
}
