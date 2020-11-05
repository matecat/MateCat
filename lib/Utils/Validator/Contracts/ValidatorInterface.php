<?php

namespace Validator\Contracts;

use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\ReviewExtended\ReviewUtils;
use Jobs_JobStruct;
use Projects_ProjectDao;
use Validator\Exception\WrongParamsException;

interface ValidatorInterface {

    /**
     * @param ValidatorObject $object
     * @param array           $params
     *
     * @throws \Exception
     * @return ValidatorObject
     */
    public function validate(ValidatorObject $object, array $params = []);
}