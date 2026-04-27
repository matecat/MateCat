<?php

namespace Utils\AsyncTasks\Workers;

use Exception;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\ChunkDao;
use Model\Segments\SegmentOriginalDataDao;
use Orhanerday\OpenAi\OpenAi;
use Predis\Client;
use ReflectionException;
use Utils\ActiveMQ\AMQHandler;
use Utils\AIAssistant\AIClientFactory;
use Utils\AIAssistant\OpenAIClient as AIAssistantClient;
use Utils\Engines\EnginesFactory;
use Utils\Engines\MyMemory;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\Tools\Utils;

class AIAssistantWorker extends AbstractWorker
{
    
    const array codeErrorsMap = [
        'NO_ERROR' => 0,
        'NO_ALTERNATIVE_TRANSLATIONS_FOUND' => 1,
        'ERROR_GENERATING_ALTERNATIVE_TRANSLATIONS' => 2,
        'NO_ERROR_MESSAGE' => 3,
        'OTHER_ERROR' => 4,
    ];
    
    const string EXPLAIN_MEANING_ACTION = 'explain_meaning';
    const string FEEDBACK_ACTION = 'feedback';
    const string ALTERNATIVE_TRANSLATIONS_ACTION = 'alternative_translations';

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
            self::FEEDBACK_ACTION,
            self::ALTERNATIVE_TRANSLATIONS_ACTION,
        ];

        if (false === in_array($action, $allowedActions)) {
            throw new EndQueueException($action . ' is not an allowed action. ');
        }

        $this->_checkDatabaseConnection();

        $this->_doLog('AI ASSISTANT: ' . $action . ' action was executed with payload ' . json_encode($payload));

        $this->{$action}($payload);
    }

    /**
     * Manages the generation and processing of alternative translations for a given payload.
     *
     * @param array $payload The input data required to generate alternative translations, including:
     *                       - localized_source: The localized source language code.
     *                       - localized_target: The localized target language code.
     *                       - source_sentence: The source sentence to translate.
     *                       - target_sentence: The target sentence to verify or enhance.
     *                       - source_context_sentences_string: Context sentences for the source language.
     *                       - target_context_sentences_string: Context sentences for the target language.
     *                       - excerpt: The text excerpt to assist in translation.
     *                       - style_instructions: Guidelines for translation style.
     *                       - id_segment: The identifier for the segment, used for logging and messaging.
     *                       - id_client*/
    private function alternative_translations(array $payload): void
    {
        try {
            $errorCode = self::codeErrorsMap['NO_ERROR'];
            $gemini = AIClientFactory::create("gemini");
            $alternativeTranslations = $gemini->manageAlternativeTranslations(
                sourceLanguage: $payload['localized_source'],
                targetLanguage:  $payload['localized_target'],
                sourceSentence:  $payload['source_sentence'],
                sourceContextSentencesString:  $payload['source_context_sentences_string'],
                targetSentence:  $payload['target_sentence'],
                targetContextSentencesString:  $payload['target_context_sentences_string'],
                excerpt:   $payload['excerpt'],
                styleInstructions:   $payload['style_instructions']
            );

            $this->_doLog("Alternative translations for id_segment " . $payload['id_segment'] . ". Requested payload " . json_encode($payload) . ", received: " . json_encode($alternativeTranslations));

            if(empty($alternativeTranslations)){
                $errorCode = self::codeErrorsMap['NO_ALTERNATIVE_TRANSLATIONS_FOUND'];
                throw new Exception("No alternative translations found");
            }

            $this->emitMessage("ai_assistant_alternative_translations", $payload['id_client'], $payload['id_segment'], $alternativeTranslations, false, true);
        } catch (Exception $exception){
            $errorCode = $errorCode ?? self::codeErrorsMap['ERROR_GENERATING_ALTERNATIVE_TRANSLATIONS'];
            $this->emitErrorMessage("ai_assistant_alternative_translations", $exception->getMessage(), $payload, $errorCode);
        }
    }

    /**
     * Processes feedback by evaluating translation and emitting relevant messages.
     *
     * @param array $payload The data containing translation details, including:
     *                       - localized_source: The source language.
     *                       - localized_target: The target language.
     *                       - text: The original text.
     *                       - translation: The translated text.
     *                       - context: The translation context.
     *                       - style: The translation style.
     *                       - id_client: The client identifier.
     *                       - id_segment: The segment identifier.
     *
     * @return void
     *
     * @throws Exception If an error occurs during the feedback processing.
     */
    private function feedback(array $payload): void
    {
        try {
            $openAi = AIClientFactory::create("openai");
            $message = $openAi->evaluateTranslation(
                sourceLanguage: $payload['localized_source'],
                targetLanguage: $payload['localized_target'],
                text: $payload['text'],
                translation: $payload['translation'],
                style: $payload['style']
            );

            $this->emitMessage("ai_assistant_feedback", $payload['id_client'], $payload['id_segment'], $message, false, true);
        } catch (Exception $e) {
            $this->emitErrorMessage("ai_assistant_feedback", $e->getMessage(), $payload);
        }
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
            $openAi = AIClientFactory::create("openai");

            $buffer = '';

            $openAi->findContextForAWord(
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
     * @param string $type
     * @param string $message
     * @param array $payload
     * @param int|null $errorCode
     *
     * @throws Exception
     */
    private function emitErrorMessage(string $type, string $message, array $payload, ?int $errorCode = 4): void
    {
        $this->_doLog($message);
        $this->emitMessage($type, $payload['id_client'], $payload['id_segment'], $message, true, true, $errorCode);
    }

    /**
     * @param string $type
     * @param string $idClient
     * @param string $idSegment
     * @param string $message
     * @param bool $hasError
     * @param bool $completed
     * @param int|null $errorCode
     *
     * @throws Exception
     */
    private function emitMessage(string $type, string $idClient, string $idSegment, null|array|string $message, bool $hasError = false, bool $completed = false, ?int $errorCode = 0): void
    {
        if($message === null){
            $errorCode = self::codeErrorsMap['NO_ERROR_MESSAGE'];
            $hasError = true;
        }

        $this->publishToNodeJsClients([
            '_type' => $type,
            'data' => [
                'id_client' => $idClient,
                'payload' => [
                    'id_segment' => $idSegment,
                    'has_error' => $hasError,
                    'error_code' => $errorCode ?? self::codeErrorsMap['NO_ERROR'],
                    'completed' => $completed,
                    'message' => is_string($message) ? trim($message) : $message
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