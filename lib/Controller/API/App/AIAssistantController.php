<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use INIT;
use Log;
use Utils;
use Utils\AsyncTasks\Workers\AIAssistantWorker;
use Utils\Langs\Languages;

class AIAssistantController extends KleinController {

    const AI_ASSISTANT_EXPLAIN_MEANING = 'AI_ASSISTANT_EXPLAIN_MEANING';

    public function index() {
        if ( empty( INIT::$OPENAI_API_KEY ) ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => 'OpenAI API key is not set'
            ] );
            die();
        }

        $json = json_decode( $this->request->body(), true );

        // target
        if ( !isset( $json[ 'target' ] ) ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => 'Missing `target` parameter'
            ] );
            die();
        }

        $languages         = Languages::getInstance();
        $localizedLanguage = $languages->getLocalizedLanguage( $json[ 'target' ] );

        if ( empty( $localizedLanguage ) ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => $json[ 'target' ] . ' is not a valid language'
            ] );
            die();
        }

        // id_segment
        if ( !isset( $json[ 'id_segment' ] ) ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => 'Missing `id_segment` parameter'
            ] );
            die();
        }

        // word
        if ( !isset( $json[ 'word' ] ) ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => 'Missing `word` parameter'
            ] );
            die();
        }

        // phrase
        if ( !isset( $json[ 'phrase' ] ) ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => 'Missing `phrase` parameter'
            ] );
            die();
        }

        // id_client
        if ( !isset( $json[ 'id_client' ] ) ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => 'Missing `id_client` parameter'
            ] );
            die();
        }

        // id_job
        if ( !isset( $json[ 'id_job' ] ) ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => 'Missing `id_job` parameter'
            ] );
            die();
        }

        // password
        if ( !isset( $json[ 'password' ] ) ) {
            $this->response->code( 500 );
            $this->response->json( [
                    'error' => 'Missing `password` parameter'
            ] );
            die();
        }

        $json = [
                'id_client'        => $json[ 'id_client' ],
                'id_segment'       => $json[ 'id_segment' ],
                'id_job'           => $json[ 'id_job' ],
                'password'         => $json[ 'password' ],
                'target'           => $json[ 'target' ],
                'localized_target' => $localizedLanguage,
                'word'             => trim( $json[ 'word' ] ),
                'phrase'           => trim( $json[ 'phrase' ] ),
        ];

        $params = [
                'action'  => AIAssistantWorker::EXPLAIN_MEANING_ACTION,
                'payload' => $json,
        ];

        $this->enqueueWorker( self::AI_ASSISTANT_EXPLAIN_MEANING, $params );

        $this->response->status()->setCode( 200 );
        $this->response->json( $json );
    }

    /**
     * @param $queue
     * @param $params
     */
    private function enqueueWorker( $queue, $params ) {
        try {
            Utils\ActiveMQ\WorkerClient::enqueue( $queue, AIAssistantWorker::class, $params, [ 'persistent' => Utils\ActiveMQ\WorkerClient::$_HANDLER->persistent ] );
        } catch ( \Exception $e ) {
            # Handle the error, logging, ...
            $output = "**** AI Assistant Worker enqueue request failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export( $params, true );
            Log::doJsonLog( $output );
            Utils::sendErrMailReport( $output );
        }
    }
}