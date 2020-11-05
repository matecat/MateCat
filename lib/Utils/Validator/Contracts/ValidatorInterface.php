<?php

namespace Validator\Contracts;

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