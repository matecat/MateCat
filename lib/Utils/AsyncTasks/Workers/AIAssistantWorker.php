<?php

namespace AsyncTasks\Workers;

use AIAssistant\Client as AIAssistantClient;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Exceptions\EndQueueException;

class AIAssistantWorker extends AbstractWorker
{
    const EXPLAIN_MEANING_ACTION  = 'explain_meaning';

    /**
     * @inheritDoc
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
     * @throws \StompException
     * @throws \Exception
     */
    private function explain_meaning($payload)
    {
        try {
            $client = $this->getAIAssistantClient();
            $message = $clstient->findContextForAWord($payload['word'], $payload['phrase'], $payload['localized_target']);
        } catch (\Exception $exception){
            $message = 'There was an error: ' . $exception->getMessage();
        }

        $this->publishMessage([
            '_type' => 'ai_assistant_explain_meaning',
            'data'  => [
                'payload'   => [
                    'message' => $message
                ],
                'id_client' => $payload[ 'id_client' ],
            ]
        ]);
    }

    /**
     * @return AIAssistantClient|null
     */
    private function getAIAssistantClient()
    {
        if(\INIT::$OPENAI_API_KEY){
            return new AIAssistantClient(\INIT::$OPENAI_API_KEY);
        }

        return null;
    }
}