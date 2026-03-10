<?php

namespace Utils\Validator;

use Controller\API\Commons\Exceptions\ValidationError;
use DomainException;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\LQA\ChunkReviewDao;
use ReflectionException;
use Utils\Validator\Contracts\AbstractValidator;
use Utils\Validator\Contracts\ValidatorObject;

class IsJobRevisionValidator extends AbstractValidator
{

    /**
     * Validates the provided ValidatorObject for specific criteria, ensuring required parameters are present
     * and verifying against the data obtained from the ChunkReviewDao.
     *
     * @param ValidatorObject $object The object to validate, containing the necessary parameters such as 'jid' and 'password'.
     * @return ValidatorObject|null Returns the validated object if criteria are met, otherwise null. Throws exceptions on validation errors.
     * @throws ValidationError If the required parameters are missing.
     * @throws DomainException
     * @throws ReflectionException
     */
    public function validate(ValidatorObject $object): ?ValidatorObject
    {
        if (!isset($object['jid'])) {
            throw new ValidationError('Missing jid parameter');
        }

        if (!isset($object['password'])) {
            throw new ValidationError('Missing password parameter');
        }

        /** @var ShapelessConcreteStruct $data */
        $data = (new ChunkReviewDao())->isTOrR1OrR2($object['jid'], $object['password']);

        if (empty($data) || ($data->t == 0 and $data->r1 == 0 and $data->r2 == 0)) {
            throw new DomainException('Invalid combination jid/password');
        }

        if ($data->r1 == 1 or $data->r2 == 1) {
            return $object;
        }

        if ($data->t != 0) {
            $this->errors[] = 'Given job password is the T password';
        }

        return null;
    }
}