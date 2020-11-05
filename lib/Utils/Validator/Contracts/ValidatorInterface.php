<?php

namespace Validator\Contracts;

use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\ReviewExtended\ReviewUtils;
use Jobs_JobStruct;
use Projects_ProjectDao;

interface ValidatorInterface {

    /**
     * @param array $params
     *
     * @return ValidatorObject
     */
    public function validate(array $params = []);
}