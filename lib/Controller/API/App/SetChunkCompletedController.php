<?php

namespace API\App;

use AbstractControllers\KleinController;
use API\Commons\Validators\LoginValidator;
use Chunks_ChunkCompletionEventStruct;
use Exception;
use Features\ProjectCompletion\CompletionEventStruct;
use Features\ProjectCompletion\Model\EventModel;
use InvalidArgumentException;
use Jobs_JobDao;
use Klein\Response;
use Utils;

class SetChunkCompletedController extends KleinController {

    protected $id_job;
    protected $received_password;

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function complete(): Response
    {
        try {
            $request = $this->validateTheRequest();

            $struct = new CompletionEventStruct( [
                'uid'               => $this->user->getUid(),
                'remote_ip_address' => Utils::getRealIpAddr(),
                'source'            => Chunks_ChunkCompletionEventStruct::SOURCE_USER,
                'is_review'         => $this->isRevision()
            ] );

            $model = new EventModel( $request['job'], $struct );
            $model->save();

            return $this->response->json([
                'data' => [
                    'event' => [
                        'id' => (int)$model->getChunkCompletionEventId()
                    ]
                ]
            ]);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return array|\Klein\Response
     * @throws \ReflectionException
     */
    private function validateTheRequest(): array
    {
        $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $received_password = filter_var( $this->request->param( 'current_password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

        if ( empty( $id_job ) ) {
            throw new InvalidArgumentException("Missing id job", -1);
        }

        if ( empty( $password ) ) {
            throw new InvalidArgumentException( "Missing id password", -2);
        }

        $job = Jobs_JobDao::getByIdAndPassword( $id_job, $password );

        if ( empty( $job ) ) {
            throw new InvalidArgumentException( "wrong password", -10);
        }

        $this->id_job = $id_job;
        $this->received_password = $received_password;

        return [
            'id_job' => $id_job,
            'password' => $password,
            'received_password' => $received_password,
            'job' => $job,
        ];
    }
}