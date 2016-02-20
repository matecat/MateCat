<?php

namespace API\V2 ;

use Features ;

class ProjectCompletionStatus extends ProtectedKleinController {

    private $validator ;

    protected function afterConstruct() {
    }

    protected function validateRequest() {
        $this->validator = new ProjectValidator(
            $this->api_record,
            $this->request->id_project
        );
        $this->validator->setFeature( 'project_completion' );

        $valid = $this->validator->validate();

        if (! $valid ) {
            $this->response->code(404);
            $this->response->json(
                array('error' => 'This project does not exist')
            );
        }
    }

    private function dataForChunkStatus ( \Chunks_ChunkStruct $chunk, $is_review ) {
        $record = \Chunks_ChunkCompletionEventDao::lastCompletionRecord( $chunk, array(
                'is_review' => $is_review
        ) );

        if ( $record != false ) {
            $is_completed = true;
            $completed_at = \Utils::api_timestamp( $record['create_date'] );
        } else {
            $is_completed = false;
            $completed_at = null;
        }

        return array(
                'id'       => $chunk->id,
                'password' => $chunk->password,
                'completed' => $is_completed,
                'completed_at' => $completed_at
        );
    }

    public function status() {
        $chunks = $this->validator->getProject()->getChunks();

        $response = array();
        $response['revise'] = array();
        $response['translate'] = array();

        $response['id'] = $this->validator->getProject()->id ;

        $any_uncomplete = false;

        foreach( $chunks as $chunk ) {
            $translate = $this->dataForChunkStatus($chunk, false); ;
            $revise = $this->dataForChunkStatus($chunk, true);

            $revise['password'] = Features::filter(
                    'filter_job_password_to_review_password',
                    $this->validator->getProject()->id_customer,
                    $chunk->password,
                    $chunk->id
            );

            $response['translate'][] = $translate ;
            $response['revise'][] = $revise ;

            if (! ( $revise['completed'] && $translate['completed'] ) ) $any_uncomplete = true;
        }

        $response['completed'] = !$any_uncomplete ;

        $this->response->json( array(
            'project_status' => $response
        ) ) ;


    }
}
