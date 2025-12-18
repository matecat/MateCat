<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use CURLFile;
use Exception;
use Model\Conversion\Upload;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
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
     * @throws Exception
     */
    public function keys(): void
    {
        $engineId = filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT);
        $params = $this->request->params();
        $MMTClient = $this->getModernMTClient($engineId);
        $memories = $MMTClient->getAllMemories();
        $results = [];

        foreach ($memories as $memory) {
            if ($this->filterResult($params, $memory)) {
                $results[] = $this->buildResult($memory);
            }
        }

        $this->response->status()->setCode(200);
        $this->response->json($results);
    }

    /**
     * Import job status
     * @throws Exception
     */
    public function importStatus(): void
    {
        $uuid = filter_var($this->request->param('uuid'), FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_STRIP_HIGH);
        $engineId = filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT);
        $MMTClient = $this->getModernMTClient($engineId);

        $this->response->status()->setCode(200);
        $this->response->json($MMTClient->importJobStatus($uuid));
    }

    /**
     * Import glossary into MMT
     * @throws Exception
     */
    public function importGlossary(): void
    {
        $this->validateImportGlossaryParams();

        if (!isset($_POST['memoryId'])) {
            throw new Exception('Missing `memoryId` param', 400);
        }

        $memoryId = filter_var($_POST['memoryId'], FILTER_SANITIZE_NUMBER_INT);

        $uploadManager = new Upload();
        $uploadedFiles = $uploadManager->uploadFiles($_FILES);

        /** @noinspection PhpUndefinedFieldInspection */
        $glossary = $this->extractCSV($uploadedFiles->glossary);


        // validate
        $csv = CSVParser::parseToArray($glossary);

        if (empty($csv)) {
            throw new Exception("Glossary is empty", 400);
        }

        $this->validateCSVContent($csv);
        $engineId = filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT);
        $MMTClient = $this->getModernMTClient($engineId);

        $this->response->status()->setCode(200);
        $this->response->json($MMTClient->importGlossary($memoryId, [
            'csv' => new CURLFile($glossary, 'text/csv'),
            'type' => $this->getCsvType($csv)
        ]));
    }

    /**
     * Update a MMT glossary (tuid is needed)
     * @throws Exception
     */
    public function modifyGlossary(): void
    {
        $this->validateModifyGlossaryParams();

        $memoryId = filter_var($this->params['memoryId'], FILTER_SANITIZE_NUMBER_INT);
        $tuid = (isset($this->params['tuid'])) ? filter_var($this->params['tuid'], FILTER_SANITIZE_SPECIAL_CHARS) : null;
        $terms = $this->params['terms'];
        $type = filter_var($this->params['type'], FILTER_SANITIZE_SPECIAL_CHARS);

        $engineId = filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT);
        $MMTClient = $this->getModernMTClient($engineId);

        $payload = [
            'type' => $type,
            'terms' => $terms
        ];

        if ($tuid) {
            $payload['tuid'] = $tuid;
        }

        $this->response->status()->setCode(200);
        $this->response->json($MMTClient->updateGlossary($memoryId, $payload));
    }

    /**
     * Update a MMT memory
     * @throws Exception
     */
    public function createMemory(): void
    {
        if (!isset($_POST['name'])) {
            throw new Exception('Missing `name` param', 400);
        }

        $name = filter_var($_POST['name'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_LOW);
        $description = isset($_POST['description']) ? filter_var($_POST['description'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_LOW) : null;
        $externalId = isset($_POST['external_id']) ? filter_var($_POST['external_id'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_LOW) : null;

        $engineId = filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT);
        $MMTClient = $this->getModernMTClient($engineId);

        $this->response->status()->setCode(200);
        $this->response->json($MMTClient->createMemory($name, $description, $externalId));
    }

    /**
     * Creates a memory, wait to be completed and then import glossary
     * @throws Exception
     */
    public function createMemoryAndImportGlossary(): void
    {
        $this->validateImportGlossaryParams();

        if (!isset($_POST['name'])) {
            throw new Exception('Missing `name` param', 400);
        }

        $name = filter_var($_POST['name'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_LOW);

        $engineId = filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT);
        $MMTClient = $this->getModernMTClient($engineId);

        // create a new memory
        $memory = $MMTClient->createMemory($name);

        // wait to be completed
        $memoryId = $memory['id'];

        // upload glossary
        $uploadManager = new Upload();
        $uploadedFiles = $uploadManager->uploadFiles($_FILES);

        /** @noinspection PhpUndefinedFieldInspection */
        $glossary = $this->extractCSV($uploadedFiles->glossary);

        // validate
        $csv = CSVParser::parseToArray($glossary);

        if (empty($csv)) {
            throw new Exception("Glossary is empty", 400);
        }

        $this->validateCSVContent($csv);

        $this->response->status()->setCode(200);
        $this->response->json($MMTClient->importGlossary($memoryId, [
            'csv' => new CURLFile($glossary, 'text/csv'),
            'type' => $this->getCsvType($csv)
        ]));
    }

    /**
     * Update a MMT memory
     * @throws Exception
     */
    public function updateMemory(): void
    {
        if (!isset($_POST['name'])) {
            throw new Exception('Missing `name` param', 400);
        }

        $name = filter_var($_POST['name'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_ENCODE_LOW);
        $memoryId = filter_var($this->request->param('memoryId'), FILTER_SANITIZE_NUMBER_INT);
        $engineId = filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT);
        $MMTClient = $this->getModernMTClient($engineId);

        $this->response->status()->setCode(200);
        $this->response->json($MMTClient->updateMemory($memoryId, $name));
    }

    /**
     * Delete a MMT memory
     * @throws Exception
     */
    public function deleteMemory(): void
    {
        $memoryId = filter_var($this->request->param('memoryId'), FILTER_SANITIZE_NUMBER_INT);
        $engineId = filter_var($this->request->param('engineId'), FILTER_SANITIZE_NUMBER_INT);
        $MMTClient = $this->getModernMTClient($engineId);

        $response = $MMTClient->deleteMemory(['id' => $memoryId]);
        $this->response->status()->setCode(200);
        $this->response->json($response);
    }

    /**
     * @throws Exception
     */
    private function validateImportGlossaryParams(): void
    {
        if (!isset($_FILES['glossary'])) {
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
     * @param $glossary
     *
     * @return false|string
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    private function extractCSV($glossary): false|string
    {
        $tmpFileName = tempnam("/tmp", "MMT_EXCEL_GLOSS_");

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
     * @param $csvContent
     *
     * @throws Exception
     */
    private function validateCSVContent($csvContent): void
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
     * @param $engineId
     *
     * @return MMT
     * @throws Exception
     */
    private function getModernMTClient($engineId): MMT
    {
        return EnginesFactory::getInstanceByIdAndUser($engineId, $this->user->uid, MMT::class);
    }

    /**
     * @param array $params
     * @param array $memory
     *
     * @return bool
     */
    private function filterResult(array $params, array $memory): bool
    {
        if (isset($params['q'])) {
            $q = filter_var($params['q'], FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW);
            if (!str_contains(strtolower($memory['name']), strtolower($q))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $memory
     *
     * @return array
     */
    private function buildResult(array $memory): array
    {
        return [
            'id' => $memory['id'],
            'name' => $memory['name'],
            'has_glossary' => ($memory['hasGlossary'] == 1),
        ];
    }

    /**
     * @param array $csv
     *
     * @return string
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
}