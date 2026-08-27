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
use InvalidArgumentException;
use TypeError;
use Utils\Validation\UserSuppliedName;

class DeepLGlossaryController extends KleinController
{

    /**
     * Not a column: the glossary lives at DeepL, not here. A bound is still needed, because without
     * one the provider decides what happens to an over-long name and MateCat cannot say what was
     * stored. 255 is the width every name MateCat does own is held to.
     */
    private const int PROVIDER_RESOURCE_NAME_MAX_LENGTH = 255;
    protected Upload $upload;

    /**
     * @throws Exception
     */
    protected function initDependencies(): void
    {
        $this->upload = new Upload();
    }

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * Get all glossaries
     * @throws DeepLApiException
     * @throws Exception
     * @throws TypeError
     */
    public function all(): void
    {
        $deepLClient = $this->getDeepLClient((int)$this->request->param('engineId'));

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
     * @throws TypeError
     * @throws InvalidArgumentException when the name is empty or will not fit
     */
    public function create(): void
    {
        $this->validateCreateGlossaryPayload();

        // FILTER_FLAG_STRIP_HIGH deleted every byte above 0x7F, so a glossary named in any
        // non-Latin script was created at DeepL with a mangled name — and the entity encoding on
        // top of it left `&amp;` there permanently. Sent as typed instead, and refused rather than
        // sent empty: a name of nothing but control characters used to reach DeepL as "".
        $name = UserSuppliedName::validated(
            is_string($this->params['name']) ? $this->params['name'] : null,
            'name',
            self::PROVIDER_RESOURCE_NAME_MAX_LENGTH
        );

        $uploadedFiles = $this->upload->uploadFiles($this->request->files()->all());

        $glossary = CSVParser::extract($uploadedFiles->glossary, "DEEPL_EXCEL_GLOSS_");
        if ($glossary === false) {
            throw new Exception("Unable to read the uploaded glossary file", 400);
        }

        // validate
        $allCSV = CSVParser::parseToArray($glossary);
        $csvHeaders = array_shift($allCSV);
        $csv = $allCSV;

        if (!is_array($csvHeaders) || count($csvHeaders) !== 2) {
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

        $deepLClient = $this->getDeepLClient((int)$this->request->param('engineId'));

        $this->response->status()->setCode(200);
        $this->response->json($deepLClient->createGlossary($data));
    }

    /**
     * @throws Exception
     */
    private function validateCreateGlossaryPayload(): void
    {
        if (!$this->request->files()->exists('glossary')) {
            throw new Exception('Missing `glossary`', 400);
        }

        if (!isset($this->params['name'])) {
            throw new Exception('Missing `name`', 400);
        }
    }

    /**
     * Delete a single glossary item
     * @throws DeepLApiException
     * @throws Exception
     * @throws TypeError
     */
    public function delete(): void
    {
        $id = (string)filter_var($this->request->param('id'), FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_ENCODE_LOW);
        $deepLClient = $this->getDeepLClient((int)$this->request->param('engineId'));

        $this->response->status()->setCode(200);
        $deepLClient->deleteGlossary($id);
        $this->response->json([
            'id' => $id
        ]);
    }

    /**
     * Get a single glossary item
     * @throws Exception
     * @throws TypeError
     */
    public function get(): void
    {
        $id = (string)filter_var($this->request->param('id'), FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_ENCODE_LOW);
        $deepLClient = $this->getDeepLClient((int)$this->request->param('engineId'));

        $this->response->status()->setCode(200);
        $this->response->json($deepLClient->getGlossary($id));
    }

    /**
     * Get a single glossary item entries
     * @throws Exception
     * @throws TypeError
     */
    public function getEntries(): void
    {
        $id = (string)filter_var($this->request->param('id'), FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_ENCODE_LOW);
        $deepLClient = $this->getDeepLClient((int)$this->request->param('engineId'));

        $this->response->status()->setCode(200);
        $this->response->json($deepLClient->getGlossaryEntries($id));
    }

    /**
     * Resolve the DeepL engine for the current user and configure its API key.
     *
     * @param int $engineId
     *
     * @return DeepL
     * @throws Exception
     */
    protected function getDeepLClient(int $engineId): DeepL
    {
        $uid = $this->user->uid ?? throw new Exception('User not authenticated', 401);

        $engine = $this->resolveDeepLEngine($engineId, $uid);
        $extraParams = $engine->getEngineRecord()->getExtraParamsAsArray();

        if (!is_array($extraParams) || !isset($extraParams['DeepL-Auth-Key'])) {
            throw new Exception('`DeepL-Auth-Key` is not set');
        }

        $engine->setApiKey((string)$extraParams['DeepL-Auth-Key']);

        return $engine;
    }

    /**
     * Resolve the DeepL engine instance from the engine registry. Isolated as a seam so
     * {@see getDeepLClient()} can be unit-tested without the live registry/DB.
     *
     * @throws Exception
     */
    protected function resolveDeepLEngine(int $engineId, int $uid): DeepL
    {
        return EnginesFactory::getInstanceByIdAndUser($engineId, $uid, $this->getDatabase(), DeepL::class);
    }
}
