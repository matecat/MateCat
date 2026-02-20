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
                throw new Exception("No alternative translations found.");
            }

            $this->emitMessage("ai_assistant_alternative_translations", $payload['id_client'], $payload['id_segment'], $alternativeTranslations, false, true);
        } catch (Exception $exception){
            $this->emitErrorMessage("ai_assistant_alternative_translations", $exception->getMessage(), $payload);
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
    private function explain_meaning(array $payload)
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
            $openAi->findContextForAWord($payload['word'], $phrase, $payload['localized_target'], function ($curl_info, $data) use (&$txt, $payload, $lockValue) {
                $currentLockValue = $this->getLockValue($payload['id_segment'], $payload['id_job'], $payload['password']);
                if ($currentLockValue !== $lockValue) {
                    $this->_doLog("Current lock invalid. Current value is: " . $currentLockValue . ", " . $lockValue . " was expected for id_segment " . $payload['id_segment']);

                    return 0;
                }

                //
                // $data returned from Open AI is a string like this:
                //
                // data: {"id":"chatcmpl-7GSjecxhnbf7oZfoYahurued904vj","object":"chat.completion.chunk","created":1684158062,"model":"gpt-4-0314","choices":[{"delta":{"role":"assistant"},"index":0,"finish_reason":null}]}
                //
                // so we need the explode here:
                //
                $_d = explode("data: ", $data);

                if (is_array($_d)) {
                    foreach ($_d as $clean) {
                        if (str_contains($data, "[DONE]\n\n")) {
                            $this->_doLog("Stream from Open Ai is terminated. Segment id:  " . $payload['id_segment']);
                            $this->emitMessage("ai_assistant_explain_meaning", $payload['id_client'], $payload['id_segment'], $txt, false, true);
                            $this->destroyLock($payload['id_segment'], $payload['id_job'], $payload['password']);

                            return 0; // exit
                        } else {
                            $this->_doLog("Received data stream from OpenAI for id_segment " . $payload['id_segment']);
                            $arr = json_decode($clean, true);

                            if ($data != "data: [DONE]\n\n" and isset($arr["choices"][0]["delta"]["content"])) {
                                $txt .= $arr["choices"][0]["delta"]["content"];
                                $this->emitMessage("ai_assistant_explain_meaning", $payload['id_client'], $payload['id_segment'], $txt);
                                // Trigger error only if $clean is not empty
                            } elseif (!empty($clean) and $clean !== '') {
                                // Trigger real errors here
                                if (Utils::isJson($clean)) {
                                    $clean = json_decode($clean, true);

                                    if (
                                        isset($clean['error']) and
                                        isset($clean['error']["message"])
                                    ) {
                                        $message = "Received wrong JSON data from OpenAI for id_segment " . $payload['id_segment'] . ":" . $clean['error']["message"] . " was received";
                                        $this->emitErrorMessage("ai_assistant_explain_meaning", $message, $payload);

                                        return 0; // exit
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $message = "Data received from OpenAI is not as array: " . $_d . " was received for id_segment " . $payload['id_segment'];
                    $this->emitErrorMessage("ai_assistant_explain_meaning", $message, $payload);

                    return 0; // exit
                }

                // NEEDED by CURLOPT_WRITEFUNCTION function
                //
                // For more info see here: https://stackoverflow.com/questions/2294344/what-for-do-we-use-curlopt-writefunction-in-phps-curl
                return strlen($data);
            });
        } catch (Exception) {
        }
    }

    /**
     * @param string $type
     * @param string $message
     * @param array $payload
     *
     * @throws Exception
     */
    private function emitErrorMessage(string $type, string $message, array $payload): void
    {
        $this->_doLog($message);
        $this->emitMessage($type, $payload['id_client'], $payload['id_segment'], $message, true);
    }

    /**
     * @param string $type
     * @param string $idClient
     * @param string $idSegment
     * @param string $message
     * @param bool $hasError
     * @param bool $completed
     *
     * @throws Exception
     */
    private function emitMessage(string $type, string $idClient, string $idSegment, null|array|string $message, bool $hasError = false, bool $completed = false): void
    {
        if($message === null){
            $hasError = true;
        }

        $this->publishToNodeJsClients([
            '_type' => $type,
            'data' => [
                'id_client' => $idClient,
                'payload' => [
                    'id_segment' => $idSegment,
                    'has_error' => $hasError,
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