<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Model\Conversion\Upload;
use Utils\Engines\DeepL;
use Utils\Engines\DeepL\DeepLApiException;
use Utils\Engines\EnginesFactory;
use Utils\Files\CSV as CSVParser;

class DeepLGlossaryController extends KleinController
{
    protected function afterConstruct(): void
    {
        parent::afterConstruct();
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * Get all glossaries
     * @throws DeepLApiException
     * @throws Exception
     */
    public function all(): void
    {
        $engineId = filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT);
        $deepLClient = $this->getDeepLClient($engineId);

        $deepLApiKey = $deepLClient->getEngineRecord()->extra_parameters['DeepL-Auth-Key'];
        $deepLClient->setApiKey($deepLApiKey);

        $this->response->status()->setCode(200);
        $this->response->json($deepLClient->glossaries());
    }

    /**
     * Create a new glossary.
     *
     * Payload example:
     *
     * {
     *   "name": "My Glossary",
     *   "source_lang": "en",
     *   "target_lang": "de",
     *   "entries": "Hello\tGuten Tag",
     *   "entries_format": "tsv"
     * }
     *
     * @throws Exception
     */
    public function create(): void
    {
        $this->validateCreateGlossaryPayload();

        $name = filter_var($_POST['name'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_STRIP_HIGH);

        $uploadManager = new Upload();
        $uploadedFiles = $uploadManager->uploadFiles($_FILES);

        /** @noinspection PhpUndefinedFieldInspection */
        $glossary = CSVParser::extract($uploadedFiles->glossary, "DEEPL_EXCEL_GLOSS_");

        // validate
        $allCSV = CSVParser::parseToArray($glossary);
        $csvHeaders = array_shift($allCSV);
        $csv = $allCSV;

        if (count($csvHeaders) !== 2) {
            throw new Exception("Glossary has more or less than 2 columns. Only bilingual files are supported", 400);
        }

        if (empty($csv)) {
            throw new Exception("Glossary is empty", 400);
        }

        $data = [
            "name" => $name,
            "source_lang" => $csvHeaders[0],
            "target_lang" => $csvHeaders[1],
            "entries" => $csv,
            "entries_format" => "csv",
        ];

        $engineId = filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT);
        $deepLClient = $this->getDeepLClient($engineId);

        $deepLApiKey = $deepLClient->getEngineRecord()->extra_parameters['DeepL-Auth-Key'];
        $deepLClient->setApiKey($deepLApiKey);

        $this->response->status()->setCode(200);
        $this->response->json($deepLClient->createGlossary($data));
    }

    /**
     * @throws Exception
     */
    private function validateCreateGlossaryPayload(): void
    {
        if (!isset($_FILES['glossary'])) {
            throw new Exception('Missing `glossary`', 400);
        }

        if (!isset($_POST['name'])) {
            throw new Exception('Missing `name`', 400);
        }
    }

    /**
     * Delete a single glossary item
     * @throws DeepLApiException
     * @throws Exception
     */
    public function delete(): void
    {
        $id = filter_var($this->request->id, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_ENCODE_LOW);
        $engineId = filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT);
        $deepLClient = $this->getDeepLClient($engineId);

        $deepLApiKey = $deepLClient->getEngineRecord()->extra_parameters['DeepL-Auth-Key'];
        $deepLClient->setApiKey($deepLApiKey);

        $this->response->status()->setCode(200);
        $deepLClient->deleteGlossary($id);
        $this->response->json([
            'id' => $id
        ]);
    }

    /**
     * Get a single glossary item
     * @throws Exception
     */
    public function get(): void
    {
        $id = filter_var($this->request->id, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_ENCODE_LOW);
        $engineId = filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT);
        $deepLClient = $this->getDeepLClient($engineId);

        $deepLApiKey = $deepLClient->getEngineRecord()->extra_parameters['DeepL-Auth-Key'];
        $deepLClient->setApiKey($deepLApiKey);

        $this->response->status()->setCode(200);
        $this->response->json($deepLClient->getGlossary($id));
    }

    /**
     * Get a single glossary item entries
     * @throws Exception
     */
    public function getEntries(): void
    {
        $id = filter_var($this->request->id, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_ENCODE_LOW);
        $engineId = filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT);
        $deepLClient = $this->getDeepLClient($engineId);

        $deepLApiKey = $deepLClient->getEngineRecord()->extra_parameters['DeepL-Auth-Key'];
        $deepLClient->setApiKey($deepLApiKey);

        $this->response->status()->setCode(200);
        $this->response->json($deepLClient->getGlossaryEntries($id));
    }

    /**
     * @param $engineId
     *
     * @return DeepL
     * @throws Exception
     */
    private function getDeepLClient($engineId): DeepL
    {
        $engine = EnginesFactory::getInstanceByIdAndUser($engineId, $this->user->uid, DeepL::class);
        $extraParams = $engine->getEngineRecord()->extra_parameters;

        if (!isset($extraParams['DeepL-Auth-Key'])) {
            throw new Exception('`DeepL-Auth-Key` is not set');
        }

        return $engine;
    }
}