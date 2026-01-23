<?php

namespace Model\Warnings;

use ArrayAccess;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\ArrayAccessTrait;
use Model\DataAccess\IDaoStruct;

class GlobalWarningStruct extends AbstractDaoSilentStruct implements IDaoStruct, ArrayAccess
{
    use ArrayAccessTrait;

    public string $id_segment;

    public string $serialized_errors_list;
}