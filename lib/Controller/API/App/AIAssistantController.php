<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Services\RateLimiterService;
use Exception;
use InvalidArgumentException;
use Klein\Response;
use Matecat\Locales\InvalidLanguageException;
use Matecat\Locales\Languages;
use Swaggest\JsonSchema\InvalidValue;
use Throwable;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\AIAssistantWorker;
use Utils\Engines\Lara;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class AIAssistantController extends KleinController
{
    const string AI_ASSISTANT_EXPLAIN_MEANING = 'AI_ASSISTANT_EXPLAIN_MEANING';

    private const int RATE_LIMIT_MAX_RETRIES = 30;

    protected RateLimiterService $rateLimiterService;

    /**
     * @throws Exception
     */
    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     */
    protected function initDependencies(): void
    {
        $this->rateLimiterService = new RateLimiterService();
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidLanguageException
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws InvalidValue
     * @throws Throwable
     */
    public function index(): void
    {
        if (empty(AppConfig::$OPENAI_API_KEY)) {
            throw new Exception('OpenAI API key not set');
        }

        if ($this->checkRateLimit('/api/app/ai-assistant')) {
            return;
        }

        $body = $this->getValidatedBody('ai_assistant_explain_meaning.json');

        $this->authorizeJob($body);

        $localizedLanguage = $this->localizedLanguageOrFail($body['target']);

        $json = [
            'id_client' => $body['id_client'],
            'id_segment' => $body['id_segment'],
            'id_job' => $body['id_job'],
            'password' => $body['password'],
            'target' => $body['target'],
            'localized_target' => $localizedLanguage,
            'word' => trim((string)$body['word']),
            'phrase' => trim((string)$body['phrase']),
        ];

        $params = [
            'action' => AIAssistantWorker::EXPLAIN_MEANING_ACTION,
            'payload' => $json,
        ];

        $this->enqueueWorker($params);

        $this->response->status()->setCode(200);
        $this->response->json($json);
    }

    /**
     * Provide a feedback on a translation
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidLanguageException
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws InvalidValue
     * @throws Throwable
     */
    public function feedback(): void
    {
        if (empty(AppConfig::$OPENAI_API_KEY)) {
            throw new Exception('OpenAI API key not set');
        }

        if ($this->checkRateLimit('/api/app/ai-assistant/feedback')) {
            return;
        }

        $body = $this->getValidatedBody('ai_assistant_feedback.json');

        $this->authorizeJob($body);

        $localizedSource = $this->localizedLanguageOrFail($body['source_language']);
        $localizedTarget = $this->localizedLanguageOrFail($body['target_language']);

        Lara::validateLaraStyle($body['style']);

        $json = [
            'id_client' => $body['id_client'],
            'localized_source' => $localizedSource,
            'localized_target' => $localizedTarget,
            'text' => trim((string)$body['text']),
            'translation' => trim((string)$body['translation']),
            'style' => trim((string)$body['style']),
            'id_segment' => $body['id_segment'],
        ];

        $params = [
            'action' => AIAssistantWorker::FEEDBACK_ACTION,
            'payload' => $json,
        ];

        $this->enqueueWorker($params);

        $this->response->status()->setCode(200);
        $this->response->json($json);
    }

    /**
     * Provide alternative translations
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws InvalidLanguageException
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws InvalidValue
     * @throws Throwable
     */
    public function alternative_translations(): void
    {
        if (empty(AppConfig::$GEMINI_API_KEY)) {
            throw new Exception('Gemini API key not set');
        }

        if ($this->checkRateLimit('/api/app/ai-assistant/alternative-translations')) {
            return;
        }

        $body = $this->getValidatedBody('ai_assistant_alternative_translations.json');

        $this->authorizeJob($body);

        $localizedSource = $this->localizedLanguageOrFail($body['source_language']);
        $localizedTarget = $this->localizedLanguageOrFail($body['target_language']);

        Lara::validateLaraStyle($body['style_instructions']);

        $json = [
            'id_client' => $body['id_client'],
            'id_job' => $body['id_job'],
            'password' => $body['password'],
            'localized_source' => $localizedSource,
            'localized_target' => $localizedTarget,
            'source_language' => $body['source_language'],
            'target_language' => $body['target_language'],
            'source_sentence' => $body['source_sentence'],
            'target_sentence' => $body['target_sentence'],
            'source_context_sentences_string' => $body['source_context_sentences_string'],
            'target_context_sentences_string' => $body['target_context_sentences_string'],
            'excerpt' => $body['excerpt'],
            'style_instructions' => $body['style_instructions'],
            'id_segment' => $body['id_segment'] ?? null,
        ];

        $params = [
            'action' => AIAssistantWorker::ALTERNATIVE_TRANSLATIONS_ACTION,
            'payload' => $json,
        ];

        $this->enqueueWorker($params);

        $this->response->status()->setCode(200);
        $this->response->json($json);
    }

    /**
     * Validate the JSON request body against the given schema and return it as an array.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     * @throws JSONValidatorException
     * @throws JsonValidatorGenericException
     * @throws InvalidValue
     * @throws Exception
     */
    protected function getValidatedBody(string $schema): array
    {
        $body = $this->request->body();
        if ($body === null || $body === '') {
            throw new InvalidArgumentException('Missing request body');
        }

        $validatorObject = new JSONValidatorObject($body);
        $validator = new JSONValidator($schema, true);
        $validator->validate($validatorObject);

        return $validatorObject->getValue(true);
    }

    /**
     * Authorize the caller against the job identified by the body's id_job + password.
     * The chunk validator coerces id_job to int and resolves the job, throwing
     * NotFoundException when the pair does not match a job the caller may access.
     *
     * @param array<string, mixed> $body
     *
     * @throws Throwable
     */
    protected function authorizeJob(array $body): void
    {
        $this->params['id_job'] = $body['id_job'];
        $this->params['password'] = $body['password'];

        (new ChunkPasswordValidator($this))->validate();
    }

    /**
     * @throws InvalidLanguageException
     * @throws Exception
     */
    protected function localizedLanguageOrFail(mixed $language): string
    {
        $localized = Languages::getInstance()->getLocalizedLanguage((string)$language);

        if (empty($localized)) {
            throw new InvalidLanguageException($language . ' is not a valid language');
        }

        return $localized;
    }

    /**
     * @throws Exception
     */
    private function checkRateLimit(string $route): bool
    {
        $identifiers = [
            Utils::getRealIpAddr() ?? '127.0.0.1',
            $this->getUser()->email ?? 'anonymous',
        ];

        foreach ($identifiers as $identifier) {
            $response = $this->rateLimiterService->checkAndIncrement(
                $this->response, $identifier, $route, self::RATE_LIMIT_MAX_RETRIES
            );
            if ($response instanceof Response) {
                $this->response = $response;

                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    protected function enqueueWorker(array $params): void
    {
        WorkerClient::enqueue(self::AI_ASSISTANT_EXPLAIN_MEANING, AIAssistantWorker::class, $params, ['persistent' => WorkerClient::$_HANDLER->persistent]);
    }
}
