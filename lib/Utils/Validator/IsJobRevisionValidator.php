<?php

namespace Validator;

use DataAccess\ShapelessConcreteStruct;
use LQA\ChunkReviewDao;
use Validator\Contracts\AbstractValidator;
use Validator\Contracts\ValidatorObject;
use Validator\Exception\LogicException;
use Validator\Exception\WrongParamsException;

class IsJobRevisionValidator extends AbstractValidator {

    /**
     * @inheritDoc
     */
    public function validate( ValidatorObject $object ) {

        if ( !isset( $object->jid ) ) {
            throw new WrongParamsException('Missing jid parameter');
        }

        if ( !isset( $object->password ) ) {
            throw new WrongParamsException('Missing jid parameter');
        }

        /** @var ShapelessConcreteStruct $data */
        $data = (new ChunkReviewDao())->isTOrR1OrR2( $object->jid, $object->password );

        if ( $data->t == 0 and $data->r1 == 0 and $data->r2 == 0 ) {
            throw new LogicException( 'Invalid combination jid/password' );
        }

        if ( $data->t == 1 ) {
            $this->errors[] = 'Given job password is the T password';

            return false;
        }

        if ( $data->r1 == 1 or $data->r2 == 1 ) {
            return true;
        }

        $this->errors[] = 'No data recevied';

        return false;
    }
}