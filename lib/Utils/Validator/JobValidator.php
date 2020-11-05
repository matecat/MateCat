<?php

namespace Validator;

use LQA\ChunkReviewDao;
use Validator\Contracts\ValidatorInterface;
use Validator\Contracts\ValidatorObject;

class JobValidator implements ValidatorInterface {

    /**
     * @inheritDoc
     */
    public function validate( array $params = [] ) {

        $validatorObject = new ValidatorObject();

        if ( !isset( $params[ 'jid' ] ) ) {
            $validatorObject->addError( 'Missing jid parameter' );
        }

        if ( !isset( $params[ 'password' ] ) ) {
            $validatorObject->addError( 'Missing password parameter' );
        }

        if ( !$validatorObject->isValid() ) {
            return $validatorObject;
        }

        $data = (new ChunkReviewDao())->getIsTOrR1OrR2( $params[ 'jid' ], $params[ 'password' ] );
        $validatorObject->setData( $data );

        if ( $data->t == 0 and $data->r1 == 0 and $data->r2 == 0 ) {
            $validatorObject->addError( 'Invalid combination jid/password' );
        }

        return $validatorObject;
    }
}