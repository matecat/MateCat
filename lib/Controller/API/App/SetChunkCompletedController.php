<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Features\ProjectCompletion\CompletionEventStruct;
use Controller\Traits\APISourcePageGuesserTrait;
use Features\ProjectCompletion\Model\EventModel;
use InvalidArgumentException;
use Jobs_JobDao;
use Model\ChunksCompletion\ChunkCompletionEventStruct;
use ReflectionException;
use Utils;

class SetChunkCompletedController extends KleinController {

    use APISourcePageGuesserTrait;

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @throws ReflectionException
     */
    public function complete(): void {

        $request = $this->validateTheRequest();

        $struct = new CompletionEventStruct( [
                'uid'               => $this->user->getUid(),
                'remote_ip_address' => Utils::getRealIpAddr(),
                'source'            => ChunkCompletionEventStruct::SOURCE_USER,
                'is_review'         => $this->isRevision()
        ] );

        $model = new EventModel( $request[ 'job' ], $struct );
        $model->save();

        $this->response->json( [
                'data' => [
                        'event' => [
                                'id' => (int)$model->getChunkCompletionEventId()
                        ]
                ]
        ] );

    }

    /**
     * @return array
     * @throws ReflectionException
     */
    private function validateTheRequest(): array {
        $id_job            = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $password          = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $received_password = filter_var( $this->request->param( 'current_password' ), FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

        if ( empty( $id_job ) ) {
            throw new InvalidArgumentException( "Missing id job", -1 );
        }

        if ( empty( $password ) ) {
            throw new InvalidArgumentException( "Missing id password", -2 );
        }

        $job = Jobs_JobDao::getByIdAndPassword( $id_job, $password );

        if ( empty( $job ) ) {
            throw new InvalidArgumentException( "wrong password", -10 );
        }

        $this->id_job           = $id_job;
        $this->request_password = $received_password;

        return [
                'id_job'            => $id_job,
                'password'          => $password,
                'received_password' => $received_password,
                'job'               => $job,
        ];
    }
}