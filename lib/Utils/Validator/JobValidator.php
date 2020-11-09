<?php

namespace Validator;

use DataAccess\ShapelessConcreteStruct;
use LQA\ChunkReviewDao;
use Validator\Contracts\ValidatorInterface;
use Validator\Contracts\ValidatorObject;
use Validator\Exception\LogicException;
use Validator\Exception\WrongParamsException;

class JobValidator implements ValidatorInterface {

    /**
     * @inheritDoc
     */
    public function validate( ValidatorObject $object, array $params = [] ) {

        if ( !isset( $params[ 'jid' ] ) ) {
            throw new WrongParamsException('Missing jid parameter');
        }

        if ( !isset( $params[ 'password' ] ) ) {
            throw new WrongParamsException('Missing jid parameter');
        }

        /** @var ShapelessConcreteStruct $data */
        $data = (new ChunkReviewDao())->isTOrR1OrR2( $params[ 'jid' ], $params[ 'password' ] );

        if ( $data->t == 0 and $data->r1 == 0 and $data->r2 == 0 ) {
            throw new LogicException( 'Invalid combination jid/password' );
        }

        $object->hydrateFromObject($data);

        return $object;
    }
}