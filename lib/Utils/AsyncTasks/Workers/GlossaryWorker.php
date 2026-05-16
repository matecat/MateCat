<?php

namespace Utils\AsyncTasks\Workers;

use Exception;
use Model\FeaturesBase\FeatureSet;
use Stomp\Exception\StompException;
use Utils\Engines\EnginesFactory;
use Utils\Engines\MyMemory;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

class GlossaryWorker extends AbstractWorker
{

    const string CHECK_ACTION = 'check';
    const string DELETE_ACTION = 'delete';
    const string GET_ACTION = 'get';
    const string KEYS_ACTION = 'keys';
    const string SET_ACTION = 'set';
    const string UPDATE_ACTION = 'update';
    const string DOMAINS_ACTION = 'domains';
    const string SEARCH_ACTION = 'search';

    /**
     * @param AbstractElement $queueElement
     *
     * @return void
     * @throws Exception
     */
    public function process(AbstractElement $queueElement): void
    {
        assert($queueElement instanceof QueueElement);

        $params = $queueElement->params->toArray();
        $action = $params['action'];
        $payload = $params['payload'];

        $this->_checkDatabaseConnection();

        $this->_doLog('GLOSSARY: ' . $action . ' action invoked with payload ' . json_encode($payload));

        switch ($action) {
            case self::CHECK_ACTION:
                $this->check($payload);
                break;
            case self::DELETE_ACTION:
                $this->delete($payload);
                break;
            case self::DOMAINS_ACTION:
                $this->domains($payload);
                break;
            case self::GET_ACTION:
                $this->get($payload);
                break;
            case self::KEYS_ACTION:
                $this->keys($payload);
                break;
            case self::SEARCH_ACTION:
                $this->search($payload);
                break;
            case self::SET_ACTION:
                $this->set($payload);
                break;
            case self::UPDATE_ACTION:
                $this->update($payload);
                break;
            default:
                throw new EndQueueException($action . ' is not an allowed action. ');
        }
    }

    /**
     * @param array{
     *     jobData: array{id: int, password: string},
     *     id_client: string,
     *     source: string,
     *     target: string,
     *     source_language: string,
     *     target_language: string,
     *     keys: array<string>,
     *     id_segment?: string
     * } $payload
     *
     * @throws Exception
     */
    private function check(array $payload): void
    {
        $client = $this->getMyMemoryClient();

        $response = $client->glossaryCheck($payload['source'], $payload['target'], $payload['source_language'], $payload['target_language'], $payload['keys']);
        $matches = $response->matches;

        if (empty($matches['id_segment'])) {
            $id_segment = $payload['id_segment'] ?? null;
            $matches['id_segment'] = $id_segment;
        }

        $this->publishToNodeJsClients(
            $this->setResponsePayload(
                'glossary_check',
                $payload['id_client'],
                $payload['jobData'],
                $matches
            )
        );
    }

    /**
     * @param array{
     *     jobData: array{id: int, password: string},
     *     id_client: string,
     *     id_job: int,
     *     password: string,
     *     term: array<string>,
     *     id_segment: string
     * } $payload
     *
     * @throws Exception
     */
    private function delete(array $payload): void
    {
        $client = $this->getMyMemoryClient();

        $response = $client->glossaryDelete($payload['id_segment'], (string) $payload['id_job'], $payload['password'], $payload['term']);

        $message = [
            'id_segment' => $payload['id_segment'],
            'payload' => null,
        ];

        if ($response->responseStatus >= 300) {
            $errMessage = "Error, please try later";

            $message['error'] = [
                'code' => $response->responseStatus,
                'message' => $errMessage,
                'payload' => $payload,
            ];
        }

        if ($response->responseStatus < 300) {
            $message['payload'] = $payload;
        }

        $this->publishToNodeJsClients(
            $this->setResponsePayload(
                'glossary_delete',
                $payload['id_client'],
                $payload['jobData'],
                $message
            )
        );
    }

    /**
     * @param array{
     *     jobData: array{id: int, password: string},
     *     id_client: string,
     *     source: string,
     *     target: string,
     *     source_language: string,
     *     target_language: string,
     *     keys: array<string>,
     *     id_segment?: string
     * } $payload
     *
     * @throws StompException
     * @throws Exception
     */
    private function domains(array $payload): void
    {
        $message = [];
        $id_segment = $payload['id_segment'] ?? null;
        $client = $this->getMyMemoryClient();

        $domains = $client->glossaryDomains($payload['keys']);

        $message['entries'] = (!empty($domains->entries)) ? $domains->entries : [];
        $message['id_segment'] = $id_segment;

        $this->publishToNodeJsClients(
            $this->setResponsePayload(
                'glossary_domains',
                $payload['id_client'],
                $payload['jobData'],
                $message
            )
        );
    }

    /**
     * @param array{
     *     jobData: array{id: int, password: string},
     *     id_client: string,
     *     id_segment: string,
     *     source: string,
     *     source_language: string,
     *     target_language: string,
     *     id_job: int,
     *     tmKeys: array<array{key: string}>
     * } $payload
     *
     * @throws EndQueueException
     * @throws Exception
     */
    private function get(array $payload): void
    {
        if (
            empty($payload['id_segment']) ||
            empty($payload['source']) ||
            empty($payload['source_language']) ||
            empty($payload['target_language'])
        ) {
            throw new EndQueueException("Invalid Payload");
        }

        $keys = [];
        foreach ($payload['tmKeys'] as $key) {
            $keys[] = $key['key'];
        }

        $client = $this->getMyMemoryClient();

        $response = $client->glossaryGet(
            (string) $payload['id_job'],
            $payload['id_segment'],
            $payload['source'],
            $payload['source_language'],
            $payload['target_language'],
            $keys
        );
        $matches = $response->matches;
        $matches = $this->formatGetGlossaryMatches($matches, $payload);

        $this->publishToNodeJsClients(
            $this->setResponsePayload(
                'glossary_get',
                $payload['id_client'],
                $payload['jobData'],
                $matches
            )
        );
    }

    /**
     * @param array{
     *     jobData: array{id: int, password: string},
     *     id_client: string,
     *     source_language: string,
     *     target_language: string,
     *     keys: array<string>
     * } $payload
     *
     * @throws Exception
     */
    private function keys(array $payload): void
    {
        $client = $this->getMyMemoryClient();

        $response = $client->glossaryKeys($payload['source_language'], $payload['target_language'], $payload['keys']);

        $this->publishToNodeJsClients(
            $this->setResponsePayload(
                'glossary_keys',
                $payload['id_client'],
                $payload['jobData'],
                [
                    'has_glossary' => $response->hasGlossary()
                ]
            )
        );
    }

    /**
     * @param array{
     *     jobData: array{id: int, password: string},
     *     id_client: string,
     *     sentence: string,
     *     source_language: string,
     *     target_language: string,
     *     tmKeys: array<array{key: string}>,
     *     id_segment?: string
     * } $payload
     *
     * @throws EndQueueException
     * @throws Exception
     */
    private function search(array $payload): void
    {
        $keys = [];
        foreach ($payload['tmKeys'] as $key) {
            $keys[] = $key['key'];
        }

        $client = $this->getMyMemoryClient();

        $response = $client->glossarySearch($payload['sentence'], $payload['source_language'], $payload['target_language'], $keys);
        $matches = $response->matches;
        $matches = $this->formatGetGlossaryMatches($matches, $payload);

        $this->publishToNodeJsClients(
            $this->setResponsePayload(
                'glossary_search',
                $payload['id_client'],
                $payload['jobData'],
                $matches
            )
        );
    }

    /**
     * @param array<int|string, mixed> $matches
     * @param array<string, mixed> $payload
     *
     * @return array<int|string, mixed>
     * @throws EndQueueException
     */
    private function formatGetGlossaryMatches(array $matches, array $payload): array
    {
        if (empty($matches)) {
            throw new EndQueueException("Empty response received from Glossary");
        }

        $idSegment = $matches['id_segment'] ?? null;
        if ($idSegment === null || $idSegment === "") {
            $matches['id_segment'] = $payload['id_segment'] ?? null;
        }

        return $matches;
    }

    /**
     * @param array{
     *     jobData: array{id: int, password: string},
     *     id_client: string,
     *     id_segment: string,
     *     id_job: int,
     *     password: string,
     *     term: array<string, mixed>,
     *     ...
     * } $payload
     *
     * @throws Exception
     */
    private function set(array $payload): void
    {
        $client = $this->getMyMemoryClient();

        $response = $client->glossarySet($payload['id_segment'], (string) $payload['id_job'], $payload['password'], $payload['term']);
        $id_segment = $payload['id_segment'] ?? null;

        $message = [
            'id_segment' => $id_segment,
            'payload' => null,
        ];

        if ($response->responseStatus >= 300) {
            $errMessage = "Error, please try later";

            $message['error'] = [
                'code' => $response->responseStatus,
                'message' => $errMessage,
                'payload' => $payload,
            ];
        }

        if ($response->responseStatus < 300) {
            $matchingWords = $payload['term']['matching_words'] ?? [];
            $matchingWordsAsArray = [];

            foreach ($matchingWords as $matchingWord) {
                $matchingWordsAsArray[] = $matchingWord;
            }

            $payload['term']['matching_words'] = $matchingWordsAsArray;

            $metadataKeys = $payload['term']['metadata']['keys'] ?? [];
            $keysAsArray = [];

            foreach ($metadataKeys as $key) {
                $keysAsArray[] = $key;
            }

            $payload['term']['metadata']['keys'] = $keysAsArray;

            $payload['request_id'] = $response->responseDetails;

            $message['payload'] = $payload;
        }

        $this->publishToNodeJsClients(
            $this->setResponsePayload(
                'glossary_set',
                $payload['id_client'],
                $payload['jobData'],
                $message
            )
        );
    }

    /**
     * @param array{
     *     jobData: array{id: int, password: string},
     *     id_client: string,
     *     id_segment: string,
     *     id_job: int,
     *     password: string,
     *     term: array<string, mixed>,
     *     ...
     * } $payload
     *
     * @throws Exception
     */
    private function update(array $payload): void
    {
        $client = $this->getMyMemoryClient();

        $response = $client->glossaryUpdate($payload['id_segment'], (string) $payload['id_job'], $payload['password'], $payload['term']);
        $id_segment = $payload['id_segment'] ?? null;

        $message = [
            'id_segment' => $id_segment,
            'payload' => null,
        ];

        if ($response->responseStatus === 202 || $response->responseStatus >= 300) {
            $errMessage = match ($response->responseStatus) {
                202 => "MyMemory is busy, please try later",
                default => "Error, please try later",
            };

            $message['error'] = [
                'code' => $response->responseStatus,
                'message' => $errMessage,
                'payload' => $payload,
            ];
        }

        if ($response->responseStatus < 300 && $response->responseStatus !== 202) {
            $matchingWords = $payload['term']['matching_words'];
            $matchingWordsAsArray = [];

            foreach ($matchingWords as $matchingWord) {
                $matchingWordsAsArray[] = $matchingWord;
            }

            $payload['term']['matching_words'] = $matchingWordsAsArray;
            $payload['request_id'] = $response->responseDetails;
            $message['payload'] = $payload;
        }

        $this->publishToNodeJsClients(
            $this->setResponsePayload(
                'glossary_update',
                $payload['id_client'],
                $payload['jobData'],
                $message
            )
        );
    }

    /**
     * @param array{id: int, password: string} $jobData
     * @param array<int|string, mixed> $message
     *
     * @return array{_type: string, data: array{payload: array<int|string, mixed>, id_client: string, id_job: int, passwords: string}}
     */
    private function setResponsePayload(string $type, string $id_client, array $jobData, array $message): array
    {
        return [
            '_type' => $type,
            'data' => [
                'payload' => $message,
                'id_client' => $id_client,
                'id_job' => $jobData['id'],
                'passwords' => $jobData['password']
            ]
        ];
    }

    /**
     * @param FeatureSet $featureSet
     * @return MyMemory
     * @throws Exception
     */
    private function getEngine(FeatureSet $featureSet): MyMemory
    {
        /** @var MyMemory $engine */
        $engine = EnginesFactory::getInstance(1, MyMemory::class);
        $engine->setFeatureSet($featureSet);

        return $engine;
    }

    /**
     * @return MyMemory
     * @throws Exception
     */
    protected function getMyMemoryClient(): MyMemory
    {
        return $this->getEngine(new FeatureSet());
    }
}
