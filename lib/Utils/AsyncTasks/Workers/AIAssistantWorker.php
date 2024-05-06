<?php

namespace AsyncTasks\Workers;

use AIAssistant\Client as AIAssistantClient;
use AMQHandler;
use Exception;
use INIT;
use Orhanerday\OpenAi\OpenAi;
use Predis\Client;
use Predis\Response\Status;
use ReflectionException;
use Stomp\Exception\StompException;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Exceptions\EndQueueException;
use Utils;

class AIAssistantWorker extends AbstractWorker {
    const EXPLAIN_MEANING_ACTION = 'explain_meaning';

    /**
     * @var OpenAi
     */
    private $openAi;

    /**
     * @var Client
     */
    private $redis;

    /**
     * AIAssistantWorker constructor.
     *
     * @param AMQHandler $queueHandler
     * @throws ReflectionException
     */
    public function __construct( AMQHandler $queueHandler ) {
        parent::__construct( $queueHandler );

        $timeOut      = ( INIT::$OPEN_AI_TIMEOUT ) ?: 30;
        $this->openAi = new OpenAi( INIT::$OPENAI_API_KEY );
        $this->openAi->setTimeout( $timeOut );

        $this->redis = $queueHandler->getRedisClient();
    }

    /**
     * @inheritDoc
     * @throws EndQueueException
     */
    public function process( AbstractElement $queueElement ) {
        $params  = $queueElement->params->toArray();
        $action  = $params[ 'action' ];
        $payload = $params[ 'payload' ];

        $allowedActions = [
                self::EXPLAIN_MEANING_ACTION,
        ];

        if ( false === in_array( $action, $allowedActions ) ) {
            throw new EndQueueException( $action . ' is not an allowed action. ' );
        }

        $this->_checkDatabaseConnection();

        $this->_doLog( 'AI ASSISTANT: ' . $action . ' action was executed with payload ' . json_encode( $payload ) );

        $this->{$action}( $payload );
    }

    /**
     * @param $payload
     *
     * @throws StompException
     * @throws Exception
     */
    private function explain_meaning( $payload ) {
        $phraseTrimLimit = ceil( INIT::$OPEN_AI_MAX_TOKENS / 2 );
        $phrase          = strip_tags( html_entity_decode( $payload[ 'phrase' ] ) );
        $phrase          = Utils::truncatePhrase( $phrase, $phraseTrimLimit );
        $txt             = "";

        $lockValue = $this->generateLockValue();

        $this->_doLog( "Preparing for OpenAI call for id_segment " . $payload[ 'id_segment' ] );
        $this->generateLock( $payload[ 'id_segment' ], $payload[ 'id_job' ], $payload[ 'password' ], $lockValue );
        $this->_doLog( "Generated lock for id_segment " . $payload[ 'id_segment' ] );

        ( new AIAssistantClient( $this->openAi ) )->findContextForAWord( $payload[ 'word' ], $phrase, $payload[ 'localized_target' ], function ( $curl_info, $data ) use ( &$txt, $payload, $lockValue ) {

            $currentLockValue = $this->getLockValue( $payload[ 'id_segment' ], $payload[ 'id_job' ], $payload[ 'password' ] );
            if ( $currentLockValue !== $lockValue ) {
                $this->_doLog( "Current lock invalid. Current value is: " . $currentLockValue . ", " . $lockValue . " was expected for id_segment " . $payload[ 'id_segment' ] );

                return 0;
            }

            //
            // $data returned from Open AI is a string like this:
            //
            // data: {"id":"chatcmpl-7GSjecxhnbf7oZfoYahurued904vj","object":"chat.completion.chunk","created":1684158062,"model":"gpt-4-0314","choices":[{"delta":{"role":"assistant"},"index":0,"finish_reason":null}]}
            //
            // so we need the explode here:
            //
            $_d = explode( "data: ", $data );

            if ( is_array( $_d ) ) {
                foreach ( $_d as $clean ) {

                    if ( strpos( $data, "[DONE]\n\n" ) !== false ) {
                        $this->_doLog( "Stream from Open Ai is terminated. Segment id:  " . $payload[ 'id_segment' ] );
                        $this->emitMessage( $payload[ 'id_client' ], $payload[ 'id_segment' ], $txt, false, true );
                        $this->destroyLock( $payload[ 'id_segment' ], $payload[ 'id_job' ], $payload[ 'password' ] );

                        return 0; // exit
                    } else {
                        $this->_doLog( "Received data stream from OpenAI for id_segment " . $payload[ 'id_segment' ] );
                        $arr = json_decode( $clean, true );

                        if ( $data != "data: [DONE]\n\n" and isset( $arr[ "choices" ][ 0 ][ "delta" ][ "content" ] ) ) {
                            $txt .= $arr[ "choices" ][ 0 ][ "delta" ][ "content" ];
                            $this->emitMessage( $payload[ 'id_client' ], $payload[ 'id_segment' ], $txt );
                        } else {
                            // Trigger error only if $clean is not empty
                            if ( !empty( $clean ) and $clean !== '' ) {

                                // Trigger real errors here
                                if ( Utils::isJson( $clean ) ) {
                                    $clean = json_decode( $clean, true );

                                    if (
                                            isset( $clean[ 'error' ] ) and
                                            isset( $clean[ 'error' ][ "message" ] )
                                    ) {
                                        $message = "Received wrong JSON data from OpenAI for id_segment " . $payload[ 'id_segment' ] . ":" . $clean[ 'error' ][ "message" ] . " was received";
                                        $this->emitErrorMessage( $message, $payload );

                                        return 0; // exit
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                $message = "Data received from OpenAI is not as array: " . $_d . " was received for id_segment " . $payload[ 'id_segment' ];
                $this->emitErrorMessage( $message, $payload );

                return 0; // exit
            }

            // NEEDED by CURLOPT_WRITEFUNCTION function
            //
            // For more info see here: https://stackoverflow.com/questions/2294344/what-for-do-we-use-curlopt-writefunction-in-phps-curl
            return strlen( $data );
        } );
    }

    /**
     * @param $message
     * @param $payload
     *
     * @throws StompException
     */
    private function emitErrorMessage( $message, $payload ) {
        $this->_doLog( $message );
        $this->emitMessage( $payload[ 'id_client' ], $payload[ 'id_segment' ], $message, true );
    }

    /**
     * @param      $idClient
     * @param      $idSegment
     * @param      $message
     * @param bool $hasError
     * @param bool $completed
     *
     * @throws StompException
     */
    private function emitMessage( $idClient, $idSegment, $message, $hasError = false, $completed = false ) {
        $this->publishToSseTopic( [
                '_type' => 'ai_assistant_explain_meaning',
                'data'  => [
                        'id_client' => $idClient,
                        'payload'   => [
                                'id_segment' => $idSegment,
                                'has_error'  => $hasError,
                                'completed'  => $completed,
                                'message'    => trim( $message )
                        ],
                ]
        ] );
    }

    /**
     * @param $idSegment
     * @param $idJob
     * @param $password
     * @param $value
     *
     * @return Status
     */
    private function generateLock( $idSegment, $idJob, $password, $value ) {
        $key = $this->getLockKey( $idSegment, $idJob, $password );

        return $this->redis->set( $key, $value );
    }

    /**
     * @param $idSegment
     * @param $idJob
     * @param $password
     *
     * @return int
     */
    private function destroyLock( $idSegment, $idJob, $password ) {
        $key = $this->getLockKey( $idSegment, $idJob, $password );

        return $this->redis->del( [ $key ] );
    }

    /**
     * @param $idSegment
     * @param $idJob
     * @param $password
     *
     * @return string
     */
    private function getLockValue( $idSegment, $idJob, $password ) {
        $key = $this->getLockKey( $idSegment, $idJob, $password );

        return $this->redis->get( $key );
    }

    /**
     * @param $idSegment
     * @param $idJob
     * @param $password
     *
     * @return string
     */
    private function getLockKey( $idSegment, $idJob, $password ) {
        return $idSegment . '-' . $idJob . '-' . $password;
    }

    /**
     * @param int $length
     *
     * @return string
     * @throws Exception
     */
    private function generateLockValue( $length = 20 ) {
        $bytes = random_bytes( $length );

        return bin2hex( $bytes );
    }
}