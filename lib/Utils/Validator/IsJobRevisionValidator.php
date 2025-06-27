<?php

namespace Validator;

use Controller\API\Commons\Exceptions\ValidationError;
use DomainException;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\LQA\ChunkReviewDao;
use Validator\Contracts\AbstractValidator;
use Validator\Contracts\ValidatorObject;

class IsJobRevisionValidator extends AbstractValidator {

    /**
     * @inheritDoc
     */
    public function validate( ValidatorObject $object ): ?ValidatorObject {

        if ( !isset( $object[ 'jid' ] ) ) {
            throw new ValidationError( 'Missing jid parameter' );
        }

        if ( !isset( $object[ 'password' ] ) ) {
            throw new ValidationError( 'Missing jid parameter' );
        }

        /** @var ShapelessConcreteStruct $data */
        $data = ( new ChunkReviewDao() )->isTOrR1OrR2( $object[ 'jid' ], $object[ 'password' ] );

        if ( empty( $data ) || ( $data->t == 0 and $data->r1 == 0 and $data->r2 == 0 ) ) {
            throw new DomainException( 'Invalid combination jid/password' );
        }

        if ( $data->r1 == 1 or $data->r2 == 1 ) {
            return $object;
        }

        if ( $data->t != 0 ) {
            $this->errors[] = 'Given job password is the T password';
        }

        return null;

    }
}