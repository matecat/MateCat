<?php

namespace AsyncTasks\Workers;

use AIAssistant\Client as AIAssistantClient;
use AMQHandler;
use Exception;
use INIT;
use Orhanerday\OpenAi\OpenAi;
use StompException;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Exceptions\EndQueueException;

class AIAssistantWorker extends AbstractWorker
{
    const EXPLAIN_MEANING_ACTION  = 'explain_meaning';

    /**
     * @var OpenAi
     */
    private $openAi;

    /**
     * @var \Predis\Client
     */
    private $redis;

    /**
     * AIAssistantWorker constructor.
     * @param AMQHandler $queueHandler
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     */
    public function __construct(AMQHandler $queueHandler)
    {
        parent::__construct($queueHandler);

        $timeOut = ( INIT::$OPEN_AI_TIMEOUT) ? INIT::$OPEN_AI_TIMEOUT : 30;
        $this->openAi = new OpenAi( INIT::$OPENAI_API_KEY );
        $this->openAi->setTimeout($timeOut);

        $this->redis = ( new \RedisHandler() )->getConnection();
    }

    /**
     * @inheritDoc
     * @throws EndQueueException
     */
    public function process(AbstractElement $queueElement)
    {
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
     * @throws StompException
     * @throws Exception
     */
    private function explain_meaning($payload)
    {
        $phrase = strip_tags( html_entity_decode( $payload['phrase'] ) );
        $txt = "";

        $lockValue = $this->generateLockValue();
        $this->generateLock($payload['id_segment'], $payload['id_job'], $payload['password'], $lockValue);

        ( new AIAssistantClient( $this->openAi ) )->findContextForAWord( $payload['word'] , $phrase, $payload['localized_target'], function ($curl_info, $data) use (&$txt, $payload, $lockValue) {

            $currentLockValue = $this->getLockValue($payload['id_segment'], $payload['id_job'], $payload['password']);
            if($currentLockValue !== $lockValue){
                return 0;
            }

            $_d = explode( "data: ", $data );

            foreach( $_d as $clean ){

                if (strpos($data, "[DONE]\n\n") !== false) {
                    $this->emitMessage( $payload['id_client'], $payload['id_segment'], $txt, false, true );
                    $this->destroyLock($payload['id_segment'], $payload['id_job'], $payload['password']);
                } else {
                    $arr = json_decode($clean, true);

                    if ($data != "data: [DONE]\n\n" and isset($arr["choices"][0]["delta"]["content"])) {
                        $txt .= $arr["choices"][0]["delta"]["content"];
                        $this->emitMessage( $payload['id_client'], $payload['id_segment'], $txt );
                    }
                }
            }

            // NEEDED by CURLOPT_WRITEFUNCTION function
            //
            // For more info see here: https://stackoverflow.com/questions/2294344/what-for-do-we-use-curlopt-writefunction-in-phps-curl
            return strlen( $data );
        } );
    }

    /**
     * @param $idClient
     * @param $idSegment
     * @param $message
     * @param bool $hasError
     * @param bool $completed
     * @throws StompException
     */
    private function emitMessage($idClient, $idSegment, $message, $hasError = false, $completed = false)
    {
        $this->publishMessage([
            '_type' => 'ai_assistant_explain_meaning',
            'data'  => [
                'id_client' => $idClient,
                'payload'   => [
                    'id_segment' => $idSegment,
                    'has_error' => $hasError,
                    'completed' => $completed,
                    'message' => trim($message)
                ],
            ]
        ]);
    }

    /**
     * @param $idSegment
     * @param $idJob
     * @param $password
     * @param $value
     * @return mixed
     */
    private function generateLock($idSegment, $idJob, $password, $value)
    {
        $key = $this->getLockKey($idSegment, $idJob, $password);

        return $this->redis->set($key, $value);
    }

    /**
     * @param $idSegment
     * @param $idJob
     * @param $password
     * @return int
     */
    private function destroyLock($idSegment, $idJob, $password)
    {
        $key = $this->getLockKey($idSegment, $idJob, $password);

        return $this->redis->del([$key]);
    }

    /**
     * @param $idSegment
     * @param $idJob
     * @param $password
     * @return string
     */
    private function getLockValue($idSegment, $idJob, $password)
    {
        $key = $this->getLockKey($idSegment, $idJob, $password);

        return $this->redis->get($key);
    }

    /**
     * @param $idSegment
     * @param $idJob
     * @param $password
     * @return string
     */
    private function getLockKey($idSegment, $idJob, $password)
    {
        return base64_encode($idSegment.'-'.$idJob.'-'.$password);
    }

    /**
     * @param int $length
     * @return string
     * @throws Exception
     */
    private function generateLockValue($length = 20)
    {
        $bytes = random_bytes($length);

        return bin2hex($bytes);
    }
}