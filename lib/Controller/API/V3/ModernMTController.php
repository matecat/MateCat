<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use CURLFile;
use Exception;
use Model\Conversion\Upload;
use Model\Conversion\UploadElement;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use RuntimeException;
use Utils\Engines\EnginesFactory;
use Utils\Engines\MMT;
use Utils\Files\CSV as CSVParser;


class ModernMTController extends KleinController
{

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * Get all the customer's MMT memories
     *
     * @throws Exception
     */
    public function keys(): void
    {
        $MMTClient = $this->getModernMTClient($this->requireEngineId());
        $params = $this->request->params();
        $memories = $MMTClient->getAllMemories();
        $results = [];

        if ($memories !== null) {
            foreach ($memories as $memory) {
                if ($this->filterResult($params, $memory)) {
                    $results[] = $this->buildResult($memory);
                }
            }
        }

        $this->response->status()->setCode(200);
        $this->response->json($results);
    }

    /**
     * Import job status
     *
     * @throws Exception
     */
    public function importStatus(): void
    {
        $uuid = filter_var($this->request->param('uuid'), FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_STRIP_HIGH);
        if (!is_string($uuid) || $uuid === '') {
            throw new Exception('Invalid `uuid` param', 400);
        }

        $MMTClient = $this->getModernMTClient($this->requireEngineId());

        $this->response->status()->setCode(200);
        $this->response->json($MMTClient->importJobStatus($uuid));
    }

    /**
     * Import glossary into MMT
     *
     * @throws Exception
     */
    public function importGlossary(): void
    {
        $this->validateImportGlossaryParams();

        if (!isset($this->params['memoryId'])) {
            throw new Exception('Missing `memoryId` param', 400);
        }

        $memoryId = filter_var($this->params['memoryId'], FILTER_SANITIZE_NUMBER_INT);
        if (!is_string($memoryId) || $memoryId === '') {
            throw new Exception('Invalid `memoryId` param', 400);
        }

        $uploadManager = $this->createUploadManager();
        $uploadedFiles = $uploadManager->uploadFiles($this->request->files()->all());

        $glossaryUpload = $uploadedFiles->glossary;
        if (!$glossaryUpload instanceof UploadElement) {
            throw new Exception('Glossary file upload failed', 400);
        }

        $glossary = $this->extractCSV($glossaryUpload);

        // validate
        $csv = CSVParser::parseToArray($glossary);

        if (empty($csv)) {
            throw new Exception("Glossary is empty", 400);
        }

        $this->validateCSVContent($csv);
        $MMTClient = $this->getModernMTClient($this->requireEngineId());

        $this->response->status()->setCode(200);
        $this->response->json($MMTClient->importGlossary($memoryId, [
            'csv'  => new CURLFile($glossary, 'text/csv'),
            'type' => $this->getCsvType($csv),
        ]));
    }

    /**
     * Update a MMT glossary (tuid is needed)
     *
     * @throws Exception
     */
    public function modifyGlossary(): void
    {
        $this->validateModifyGlossaryParams();

        $memoryId = filter_var($this->params['memoryId'], FILTER_SANITIZE_NUMBER_INT);
        if (!is_string($memoryId) || $memoryId === '') {
            throw new Exception('Invalid `memoryId` param', 400);
        }

        $tuid = isset($this->params['tuid'])
            ? filter_var($this->params['tuid'], FILTER_SANITIZE_SPECIAL_CHARS)
            : null;
        if ($tuid === false) {
            $tuid = null;
        }

        $terms = $this->params['terms'];

        $type = filter_var($this->params['type'], FILTER_SANITIZE_SPECIAL_CHARS);
        if (!is_string($type) || $type === '') {
            throw new Exception('Invalid `type` param', 400);
        }

        $MMTClient = $this->getModernMTClient($this->requireEngineId());

        $payload = [
            'type'  => $type,
            'terms' => $terms,
        ];

        if ($tuid) {
            $payload['tuid'] = $tuid;
        }

        $this->response->status()->setCode(200);
        $this->response->json($MMTClient->updateGlossary($memoryId, $payload));
    }

    /**
     * Create a MMT memory
     *
     * @throws Exception
     */
    public function createMemory(): void
    {
        if (!isset($this->params['name'])) {
            throw new Exception('Missing `name` param', 400);
        }

        $name = filter_var($this->params['name'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_LOW);
        if (!is_string($name) || $name === '') {
            throw new Exception('Invalid `name` param', 400);
        }

        $descriptionRaw = isset($this->params['description'])
            ? filter_var($this->params['description'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_LOW)
            : null;
        $description = is_string($descriptionRaw) ? $descriptionRaw : null;

        $externalIdRaw = isset($this->params['external_id'])
            ? filter_var($this->params['external_id'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_LOW)
            : null;
        $externalId = is_string($externalIdRaw) ? $externalIdRaw : null;

        $MMTClient = $this->getModernMTClient($this->requireEngineId());

        $this->response->status()->setCode(200);
        $this->response->json($MMTClient->createMemory($name, $description, $externalId));
    }

    /**
     * Creates a memory, wait to be completed and then import glossary
     *
     * @throws Exception
     */
    public function createMemoryAndImportGlossary(): void
    {
        $this->validateImportGlossaryParams();

        if (!isset($this->params['name'])) {
            throw new Exception('Missing `name` param', 400);
        }

        $name = filter_var($this->params['name'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_LOW);
        if (!is_string($name) || $name === '') {
            throw new Exception('Invalid `name` param', 400);
        }

        $MMTClient = $this->getModernMTClient($this->requireEngineId());

        // create a new memory
        $memory = $MMTClient->createMemory($name);
        if ($memory === null || !isset($memory['id'])) {
            throw new RuntimeException('Failed to create MMT memory');
        }

        // wait to be completed
        $memoryId = (string) $memory['id'];

        // upload glossary
        $uploadManager = $this->createUploadManager();
        $uploadedFiles = $uploadManager->uploadFiles($this->request->files()->all());

        $glossaryUpload = $uploadedFiles->glossary;
        if (!$glossaryUpload instanceof UploadElement) {
            throw new Exception('Glossary file upload failed', 400);
        }

        $glossary = $this->extractCSV($glossaryUpload);

        // validate
        $csv = CSVParser::parseToArray($glossary);

        if (empty($csv)) {
            throw new Exception("Glossary is empty", 400);
        }

        $this->validateCSVContent($csv);

        $this->response->status()->setCode(200);
        $this->response->json($MMTClient->importGlossary($memoryId, [
            'csv'  => new CURLFile($glossary, 'text/csv'),
            'type' => $this->getCsvType($csv),
        ]));
    }

    /**
     * Update a MMT memory
     *
     * @throws Exception
     */
    public function updateMemory(): void
    {
        if (!isset($this->params['name'])) {
            throw new Exception('Missing `name` param', 400);
        }

        $name = filter_var($this->params['name'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_LOW);
        if (!is_string($name) || $name === '') {
            throw new Exception('Invalid `name` param', 400);
        }

        $memoryId = filter_var($this->request->param('memoryId'), FILTER_SANITIZE_NUMBER_INT);
        if (!is_string($memoryId) || $memoryId === '') {
            throw new Exception('Invalid `memoryId` param', 400);
        }

        $MMTClient = $this->getModernMTClient($this->requireEngineId());

        $this->response->status()->setCode(200);
        $this->response->json($MMTClient->updateMemory($memoryId, $name));
    }

    /**
     * Delete a MMT memory
     *
     * @throws Exception
     */
    public function deleteMemory(): void
    {
        $memoryId = filter_var($this->request->param('memoryId'), FILTER_SANITIZE_NUMBER_INT);
        if (!is_string($memoryId) || $memoryId === '') {
            throw new Exception('Invalid `memoryId` param', 400);
        }

        $MMTClient = $this->getModernMTClient($this->requireEngineId());

        $response = $MMTClient->deleteMemory(['id' => $memoryId]);
        $this->response->status()->setCode(200);
        $this->response->json($response);
    }

    /**
     * @throws Exception
     */
    private function validateImportGlossaryParams(): void
    {
        if (!$this->request->files()->exists('glossary')) {
            throw new Exception('Missing `glossary` files', 400);
        }
    }

    /**
     * @throws Exception
     */
    private function validateModifyGlossaryParams(): void
    {
        if (!isset($this->params['memoryId'])) {
            throw new Exception('Missing `memoryId` param', 400);
        }

        if (!isset($this->params['type'])) {
            throw new Exception('Missing `type` param', 400);
        }

        $allowedTypes = [
            'unidirectional',
            'equivalent',
        ];

        if (!in_array($this->params['type'], $allowedTypes)) {
            throw new Exception('Wrong `type` param. Allowed values: [unidirectional, equivalent]', 400);
        }

        if ($this->params['type'] === 'equivalent' and !isset($this->params['tuid'])) {
            throw new Exception('Missing `tuid` param', 400);
        }

        if (!isset($this->params['terms'])) {
            throw new Exception('Missing `terms` param', 400);
        }

        // validate terms
        $terms = $this->params['terms'];

        if (!is_array($terms)) {
            throw new Exception('`terms` is not an array', 400);
        }

        foreach ($terms as $term) {
            if (!isset($term['term']) or !isset($term['language'])) {
                throw new Exception('`terms` array is malformed.', 400);
            }
        }
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws RuntimeException
     */
    protected function extractCSV(UploadElement $glossary): string
    {
        $tmpFileName = tempnam("/tmp", "MMT_EXCEL_GLOSS_");
        if ($tmpFileName === false) {
            throw new RuntimeException('Failed to create temporary file');
        }

        $objReader = IOFactory::createReaderForFile($glossary->file_path);

        $objPHPExcel = $objReader->load($glossary->file_path);
        $objWriter = new Csv($objPHPExcel);
        $objWriter->save($tmpFileName);

        $oldPath = $glossary->file_path;
        $glossary->file_path = $tmpFileName;

        unlink($oldPath);

        return $glossary->file_path;
    }

    /**
     * @param array<int, array<int, string>> $csvContent
     *
     * @throws Exception
     */
    private function validateCSVContent(array $csvContent): void
    {
        $type = $this->getCsvType($csvContent);

        foreach ($csvContent as $csvRowIndex => $csvRow) {
            // missing tuid (for equivalent)
            if ($type === 'equivalent' and empty($csvRow[0])) {
                throw new Exception("Row " . ($csvRowIndex + 1) . " invalid, please provide a tuid for the row.");
            }

            $emptyCells = 0;

            for ($i = 0; $i < count($csvRow); $i++) {
                // empty cells
                if (empty($csvRow[$i])) {
                    $emptyCells++;
                    if ($type === 'unidirectional') {
                        // empty cell (for unidirectional)
                        throw new Exception("Row " . ($csvRowIndex + 1) . " invalid, please add terms for both languages.");
                    }
                }
            }

            // cells has only one term (for equivalent)
            if ($type === 'equivalent' and ($emptyCells >= (count($csvRow) - 2))) {
                throw new Exception("Row " . ($csvRowIndex + 1) . " invalid, please provide terms for at least two languages.");
            }
        }
    }

    /**
     * @throws Exception
     * @throws RuntimeException
     */
    protected function getModernMTClient(int $engineId): MMT
    {
        $uid = $this->user->uid;
        if ($uid === null) {
            throw new RuntimeException('User not authenticated');
        }

        return EnginesFactory::getInstanceByIdAndUser($engineId, $uid, MMT::class);
    }

    /**
     * @param array<int|string, mixed> $params
     * @param array<string, mixed> $memory
     */
    private function filterResult(array $params, array $memory): bool
    {
        if (isset($params['q'])) {
            $q = filter_var($params['q'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);
            if (is_string($q) && isset($memory['name']) && is_string($memory['name'])
                && !str_contains(strtolower($memory['name']), strtolower($q))
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $memory
     *
     * @return array{id: mixed, name: mixed, has_glossary: bool}
     */
    private function buildResult(array $memory): array
    {
        return [
            'id'           => $memory['id'],
            'name'         => $memory['name'],
            'has_glossary' => ($memory['hasGlossary'] == 1),
        ];
    }

    /**
     * @param array<int, array<int, string>> $csv
     *
     * @throws Exception
     */
    private function getCsvType(array $csv): string
    {
        $firstCell = $csv[0][0];
        $numberOfRows = count($csv[0]);

        if ($numberOfRows === 1) {
            throw new Exception("Glossary invalid: unidirectional glossaries should have exactly two columns");
        }

        // tuid and 2 columns
        if ($firstCell == 'tuid' and $numberOfRows <= 2) {
            throw new Exception("Glossary invalid: at least two language columns are expected for glossaries of equivalent terms");
        }

        // tuid and more than 2 columns
        if ($firstCell == 'tuid' and $numberOfRows > 2) {
            return 'equivalent';
        }

        // if is not equivalent and there are more than 2 columns, is not valid
        if ($numberOfRows > 2) {
            throw new Exception("Glossary invalid: tuid column is expected for glossaries of equivalent terms");
        }

        return 'unidirectional';
    }

    /**
     * @throws Exception
     */
    private function requireEngineId(): int
    {
        $raw = filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT);
        if (!is_string($raw) || $raw === '') {
            throw new Exception('Invalid `engineId` param', 400);
        }

        return (int) $raw;
    }

    /**
     * @throws Exception
     */
    protected function createUploadManager(): Upload
    {
        return new Upload();
    }
}
