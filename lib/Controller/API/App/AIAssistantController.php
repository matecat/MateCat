<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Exception;
use InvalidArgumentException;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\AIAssistantWorker;
use Utils\Engines\Lara;
use Utils\Langs\InvalidLanguageException;
use Utils\Langs\Languages;
use Utils\Registry\AppConfig;

class AIAssistantController extends KleinController
{
    const string AI_ASSISTANT_EXPLAIN_MEANING = 'AI_ASSISTANT_EXPLAIN_MEANING';

    /**
     * @throws Exception
     */
    public function index(): void
    {
        if (empty(AppConfig::$OPENAI_API_KEY)) {
            throw new Exception('OpenAI API key not set');
        }

        $json = json_decode($this->request->body(), true);

        // target
        if (!isset($json['target'])) {
            throw new InvalidArgumentException('Missing `target` parameter');
        }

        $languages = Languages::getInstance();
        $localizedLanguage = $languages->getLocalizedLanguage($json['target']);

        if (empty($localizedLanguage)) {
            throw new InvalidLanguageException($json['target'] . ' is not a valid language');
        }

        // id_segment
        if (!isset($json['id_segment'])) {
            throw new InvalidArgumentException('Missing `id_segment` parameter');
        }

        // word
        if (!isset($json['word'])) {
            throw new InvalidArgumentException('Missing `word` parameter');
        }

        // phrase
        if (!isset($json['phrase'])) {
            throw new InvalidArgumentException('Missing `phrase` parameter');
        }

        // id_client
        if (!isset($json['id_client'])) {
            throw new InvalidArgumentException('Missing `id_client` parameter');
        }

        // id_job
        if (!isset($json['id_job'])) {
            throw new InvalidArgumentException('Missing `id_job` parameter');
        }

        // password
        if (!isset($json['password'])) {
            throw new InvalidArgumentException('Missing `password` parameter');
        }

        $json = [
            'id_client' => $json['id_client'],
            'id_segment' => $json['id_segment'],
            'id_job' => $json['id_job'],
            'password' => $json['password'],
            'target' => $json['target'],
            'localized_target' => $localizedLanguage,
            'word' => trim($json['word']),
            'phrase' => trim($json['phrase']),
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
     */
    public function feedback(): void
    {
        if (empty(AppConfig::$OPENAI_API_KEY)) {
            throw new Exception('OpenAI API key not set');
        }

        $json = json_decode($this->request->body(), true);

        // source
        if (!isset($json['source_language'])) {
            throw new InvalidArgumentException('Missing `source_language` parameter');
        }

        // target
        if (!isset($json['target_language'])) {
            throw new InvalidArgumentException('Missing `target_language` parameter');
        }

        $languages = Languages::getInstance();
        $localizedSource = $languages->getLocalizedLanguage($json['source_language']);
        $localizedTarget = $languages->getLocalizedLanguage($json['target_language']);

        if (empty($localizedSource)) {
            throw new InvalidLanguageException($json['source_language'] . ' is not a valid language');
        }

        if (empty($localizedTarget)) {
            throw new InvalidLanguageException($json['target_language'] . ' is not a valid language');
        }

        // text
        if (!isset($json['text'])) {
            throw new InvalidArgumentException('Missing `text` parameter');
        }

        // translation
        if (!isset($json['translation'])) {
            throw new InvalidArgumentException('Missing `translation` parameter');
        }

        // context
        if (!isset($json['context'])) {
            throw new InvalidArgumentException('Missing `context` parameter');
        }

        // style
        if (!isset($json['style'])) {
            throw new InvalidArgumentException('Missing `style` parameter');
        }

        // id_client
        if (!isset($json['id_client'])) {
            throw new InvalidArgumentException('Missing `id_client` parameter');
        }

        // id_segment
        if (!isset($json['id_segment'])) {
            throw new InvalidArgumentException('Missing `id_segment` parameter');
        }

        $json = [
            'id_client' => $json['id_client'],
            'localized_source' => $localizedSource,
            'localized_target' => $localizedTarget,
            'text' => trim($json['text']),
            'translation' => trim($json['translation']),
            'context' => trim($json['context']),
            'style' => trim($json['style']),
            'id_segment' => $json['id_segment'],
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
     */
    public function alternative_translations(): void
    {
        if (empty(AppConfig::$GEMINI_API_KEY)) {
            throw new Exception('Gemini API key not set');
        }

        $json = json_decode($this->request->body(), true);

        // source
        if (!isset($json['source_language'])) {
            throw new InvalidArgumentException('Missing `source_language` parameter');
        }

        // target
        if (!isset($json['target_language'])) {
            throw new InvalidArgumentException('Missing `target_language` parameter');
        }

        $languages = Languages::getInstance();
        $localizedSource = $languages->getLocalizedLanguage($json['source_language']);
        $localizedTarget = $languages->getLocalizedLanguage($json['target_language']);

        if (empty($localizedSource)) {
            throw new InvalidLanguageException($json['source_language'] . ' is not a valid language');
        }

        if (empty($localizedTarget)) {
            throw new InvalidLanguageException($json['target_language'] . ' is not a valid language');
        }

        // source_sentence
        if (!isset($json['source_sentence'])) {
            throw new InvalidArgumentException('Missing `source_sentence` parameter');
        }

        // target_sentence
        if (!isset($json['target_sentence'])) {
            throw new InvalidArgumentException('Missing `target_sentence` parameter');
        }

        // source_context_sentences_string
        if (!isset($json['source_context_sentences_string'])) {
            throw new InvalidArgumentException('Missing `source_context_sentences_string` parameter');
        }

        // target_context_sentences_string
        if (!isset($json['target_context_sentences_string'])) {
            throw new InvalidArgumentException('Missing `target_context_sentences_string` parameter');
        }

        // context
        if (!isset($json['excerpt'])) {
            throw new InvalidArgumentException('Missing `excerpt` parameter');
        }

        // style
        if (!isset($json['style_instructions'])) {
            throw new InvalidArgumentException('Missing `style_instructions` parameter');
        }

        Lara::validateLaraStyle($json['style_instructions']);

        // id_client
        if (!isset($json['id_client'])) {
            throw new InvalidArgumentException('Missing `id_client` parameter');
        }

        // id_segment
        if (!isset($json['id_segment'])) {
            throw new InvalidArgumentException('Missing `id_segment` parameter');
        }

        // id_job
        if (!isset($json['id_job'])) {
            throw new InvalidArgumentException('Missing `id_job` parameter');
        }

        // password
        if (!isset($json['password'])) {
            throw new InvalidArgumentException('Missing `password` parameter');
        }

        $json = [
            'id_client' => $json['id_client'],
            'id_job' => $json['id_job'],
            'password' => $json['password'],
            'localized_source' => $localizedSource,
            'localized_target' => $localizedTarget,
            'source_language' => $json['source_language'],
            'target_language' => $json['target_language'],
            'source_sentence' => $json['source_sentence'],
            'target_sentence' => $json['target_sentence'],
            'source_context_sentences_string' => $json['source_context_sentences_string'],
            'target_context_sentences_string' => $json['target_context_sentences_string'],
            'excerpt' => $json['excerpt'],
            'style_instructions' => $json['style_instructions'],
            'id_segment' => $json['id_segment'] ?? null,
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
     * @param array $params
     *
     * @throws Exception
     */
    private function enqueueWorker(array $params): void
    {
        WorkerClient::enqueue(self::AI_ASSISTANT_EXPLAIN_MEANING, AIAssistantWorker::class, $params, ['persistent' => WorkerClient::$_HANDLER->persistent]);
    }
}