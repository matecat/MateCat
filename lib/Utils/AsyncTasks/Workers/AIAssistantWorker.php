<?php

namespace Utils\AsyncTasks\Workers;

use Exception;
use Orhanerday\OpenAi\OpenAi;
use Predis\Client;
use ReflectionException;
use Utils\ActiveMQ\AMQHandler;
use Utils\AIAssistant\Client as AIAssistantClient;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\Tools\Utils;

class AIAssistantWorker extends AbstractWorker
{
    const string EXPLAIN_MEANING_ACTION = 'explain_meaning';

    /**
     * @var OpenAi
     */
    private OpenAi $openAi;

    /**
     * @var Client
     */
    private Client $redis;

    /**
     * AIAssistantWorker constructor.
     *
     * @param AMQHandler $queueHandler
     *
     * @throws ReflectionException
     */
    public function __construct(AMQHandler $queueHandler)
    {
        parent::__construct($queueHandler);

        $timeOut = (AppConfig::$OPEN_AI_TIMEOUT) ?: 30;
        $this->openAi = new OpenAi(AppConfig::$OPENAI_API_KEY);
        $this->openAi->setTimeout($timeOut);

        $this->redis = $queueHandler->getRedisClient();
    }

    /**
     * @inheritDoc
     * @throws EndQueueException
     */
    public function process(AbstractElement $queueElement): void
    {
        $params = $queueElement->params->toArray();
        $action = $params['action'];
        $payload = $params['payload'];

        $allowedActions = [
            self::EXPLAIN_MEANING_ACTION,
        ];

        if (false === in_array($action, $allowedActions)) {
            throw new EndQueueException($action . ' is not an allowed action. ');
        }

        $this->_checkDatabaseConnection();

        $this->_doLog('AI ASSISTANT: ' . $action . ' action was executed with payload ' . json_encode($payload));

        $this->{$action}($payload);
    }

    /**
     * @param array $payload
     *
     * @throws Exception
     */
    private function explain_meaning(array $payload): void
    {
        $phraseTrimLimit = ceil(AppConfig::$OPEN_AI_MAX_TOKENS / 2);
        $phrase = strip_tags(html_entity_decode($payload['phrase']));
        $phrase = Utils::truncatePhrase($phrase, $phraseTrimLimit);
        $txt = "";

        $lockValue = $this->generateLockValue();

        $this->_doLog("Preparing for OpenAI call for id_segment " . $payload['id_segment']);
        $this->generateLock($payload['id_segment'], $payload['id_job'], $payload['password'], $lockValue);
        $this->_doLog("Generated lock for id_segment " . $payload['id_segment']);

        try {
            $buffer = '';

            (new AIAssistantClient($this->openAi))->findContextForAWord(
                $payload['word'],
                $phrase,
                $payload['localized_target'],
                function ($curl_info, $data) use (&$txt, &$buffer, $payload, $lockValue) {

                    // Check lock
                    $currentLockValue = $this->getLockValue(
                        $payload['id_segment'],
                        $payload['id_job'],
                        $payload['password']
                    );

                    if ($currentLockValue !== $lockValue) {
                        $this->_doLog("Lock invalid for id_segment " . $payload['id_segment']);
                        return strlen($data); // do not stop curl
                    }

                    // Collect chunks
                    $buffer .= $data;

                    // Processing of a completed event (SSE = separate from \n\n)
                    while (($pos = strpos($buffer, "\n\n")) !== false) {

                        $event = substr($buffer, 0, $pos);
                        $buffer = substr($buffer, $pos + 2);

                        // every row should start with "data: "
                        if (!str_starts_with($event, 'data:')) {
                            continue;
                        }

                        $json = trim(substr($event, 5)); // remove "data:"

                        // End of stream
                        if ($json === '[DONE]') {
                            $this->_doLog("Stream completed for id_segment " . $payload['id_segment']);

                            $this->emitMessage(
                                "ai_assistant_explain_meaning",
                                $payload['id_client'],
                                $payload['id_segment'],
                                $txt,
                                false,
                                true
                            );

                            $this->destroyLock(
                                $payload['id_segment'],
                                $payload['id_job'],
                                $payload['password']
                            );

                            return strlen($data);
                        }

                        // Parse JSON
                        $arr = json_decode($json, true);

                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $this->_doLog("Invalid JSON chunk: " . $json);
                            continue;
                        }

                        // Content
                        if (isset($arr["choices"][0]["delta"]["content"])) {
                            $txt .= $arr["choices"][0]["delta"]["content"];

                            $this->emitMessage(
                                "ai_assistant_explain_meaning",
                                $payload['id_client'],
                                $payload['id_segment'],
                                $txt
                            );
                        }

                        // OpenAI errors
                        if (isset($arr['error']['message'])) {
                            $message = "OpenAI error: " . $arr['error']['message'];

                            $this->emitErrorMessage(
                                "ai_assistant_explain_meaning",
                                $message,
                                $payload
                            );

                            return strlen($data);
                        }
                    }

                    // ✅ Continua lo stream
                    return strlen($data);
                }
            );


        } catch (Exception) {
        }
    }

    /**
     * @param string $message
     * @param array $payload
     *
     * @throws Exception
     */
    private function emitErrorMessage(string $message, array $payload): void
    {
        $this->_doLog($message);
        $this->emitMessage($payload['id_client'], $payload['id_segment'], $message, true);
    }

    /**
     * @param string $idClient
     * @param string $idSegment
     * @param string $message
     * @param bool $hasError
     * @param bool $completed
     *
     * @throws Exception
     */
    private function emitMessage(string $idClient, string $idSegment, string $message, bool $hasError = false, bool $completed = false): void
    {
        $this->publishToNodeJsClients([
            '_type' => 'ai_assistant_explain_meaning',
            'data' => [
                'id_client' => $idClient,
                'payload' => [
                    'id_segment' => $idSegment,
                    'has_error' => $hasError,
                    'completed' => $completed,
                    'message' => trim($message)
                ],
            ]
        ]);
    }

    /**
     * @param string $idSegment
     * @param int $idJob
     * @param string $password
     * @param string $value
     *
     * @return void
     */
    private function generateLock(string $idSegment, int $idJob, string $password, string $value): void
    {
        $key = $this->getLockKey($idSegment, $idJob, $password);

        $this->redis->set($key, $value);
    }

    /**
     * @param string $idSegment
     * @param int $idJob
     * @param string $password
     *
     * @return void
     */
    private function destroyLock(string $idSegment, int $idJob, string $password): void
    {
        $key = $this->getLockKey($idSegment, $idJob, $password);

        $this->redis->del([$key]);
    }

    /**
     * @param string $idSegment
     * @param int $idJob
     * @param string $password
     *
     * @return string
     */
    private function getLockValue(string $idSegment, int $idJob, string $password): string
    {
        $key = $this->getLockKey($idSegment, $idJob, $password);

        return $this->redis->get($key);
    }

    /**
     * @param string $idSegment
     * @param int $idJob
     * @param string $password
     *
     * @return string
     */
    private function getLockKey(string $idSegment, int $idJob, string $password): string
    {
        return $idSegment . '-' . $idJob . '-' . $password;
    }

    /**
     *
     * @return string
     * @throws Exception
     */
    private function generateLockValue(): string
    {
        $bytes = random_bytes(20);

        return bin2hex($bytes);
    }
}