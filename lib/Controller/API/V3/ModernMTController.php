<?php

namespace API\V3;

use API\V2\Validators\LoginValidator;
use CURLFile;
use Engines_MMT;
use Exception;
use Files\CSV as CSVParser;
use API\V2\BaseChunkController;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Upload;
use Validator\EngineValidator;


class ModernMTController extends BaseChunkController
{
    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Get all the customer's MMT memories
     */
    public function get()
    {
        if(!$this->userIsLogged()){
            $this->response->status()->setCode( 401 );
            $this->response->json([]);
            exit();
        }

        try {
            $engineId = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );
            $params = $this->request->params();
            $MMTClient = $this->getModernMTClient($engineId);
            $memories = $MMTClient->getAllMemories();
            $results = [];

            foreach ($memories as $memory){
                if($this->filterResult($params, $memory)){
                    $results[] = $this->buildResult($memory);
                }
            }

            $this->response->status()->setCode( 200 );
            $this->response->json($results);
            exit();

        } catch (Exception $exception){
            $this->response->status()->setCode( 500 );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    /**
     * Import glossary into MMT
     */
    public function importGlossary()
    {
        try {
            $this->validateImportGlossaryParams();

            $memoryId = filter_var( $_POST['memoryId' ], FILTER_SANITIZE_NUMBER_INT );
            $type = filter_var( $_POST['type' ], FILTER_SANITIZE_STRING );

            $uploadManager = new Upload();
            $uploadedFiles = $uploadManager->uploadFiles( $_FILES );

            $glossary = $this->extractCSVAndValidate($uploadedFiles->glossary, $type);

            $engineId = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );
            $MMTClient = $this->getModernMTClient($engineId);

            $this->response->status()->setCode( 200 );
            $this->response->json($MMTClient->importGlossary($memoryId, [
                'csv' => new CURLFile($glossary, 'text/csv'),
                'type' => $type
            ]));
            exit();

        } catch (Exception $exception){
            $this->response->status()->setCode( 500 );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    /**
     * Update a MMT glossary (tuid is needed)
     */
    public function modifyGlossary()
    {
        try {
            $this->validateModifyGlossaryParams();

            $memoryId = filter_var( $this->params['memoryId' ], FILTER_SANITIZE_NUMBER_INT );
            $tuid = (isset($this->params['tuid'])) ? filter_var( $this->params['tuid' ], FILTER_SANITIZE_STRING ) : null;
            $terms = $this->params['terms'];
            $type = filter_var( $this->params['type' ], FILTER_SANITIZE_STRING );

            $engineId = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );
            $MMTClient = $this->getModernMTClient($engineId);

            $payload = [
                'type'  => $type,
                'terms' => $terms
            ];

            if($tuid){
                $payload['tuid'] = $tuid;
            }

            $this->response->status()->setCode( 200 );
            $this->response->json($MMTClient->updateGlossary($memoryId, $payload));
            exit();

        } catch (Exception $exception){
            $this->response->status()->setCode( 500 );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    /**
     * Delete a MMT memory
     */
    public function deleteMemory()
    {
        try {
            $memoryId = filter_var( $this->request->memoryId, FILTER_SANITIZE_NUMBER_INT );
            $engineId = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );
            $MMTClient = $this->getModernMTClient($engineId);

            $this->response->status()->setCode( 200 );
            $this->response->json($MMTClient->deleteMemory($memoryId));
            exit();

        } catch (Exception $exception){
            $this->response->status()->setCode( 500 );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    /**
     * @throws Exception
     */
    private function validateImportGlossaryParams()
    {
        if(!isset($_FILES['glossary'])){
            throw new Exception('Missing `glossary` files');
        }

        if(!isset($_POST['memoryId'])){
            throw new Exception('Missing `memoryId` param');
        }

        if(!isset($_POST['type']) ){
            throw new Exception('Missing `type` param');
        }

        $allowedTypes = [
            'unidirectional',
            'equivalent',
        ];

        if(!in_array($_POST['type'], $allowedTypes)){
            throw new Exception('Wrong `type` param. Allowed values: [unidirectional, equivalent]');
        }
    }

    /**
     * @throws Exception
     */
    private function validateModifyGlossaryParams()
    {
        if(!isset($this->params['memoryId'])){
            throw new Exception('Missing `memoryId` param');
        }

        if(!isset($this->params['type']) ){
            throw new Exception('Missing `type` param');
        }

        $allowedTypes = [
            'unidirectional',
            'equivalent',
        ];

        if(!in_array($this->params['type'], $allowedTypes)){
            throw new Exception('Wrong `type` param. Allowed values: [unidirectional, equivalent]');
        }

        if($this->params['type'] === 'equivalent' and !isset($this->params['tuid'])){
            throw new Exception('Missing `tuid` param');
        }

        if(!isset($this->params['terms'])){
            throw new Exception('Missing `terms` param');
        }

        // validate terms
        $terms = $this->params['terms'];

        if(!is_array($terms)){
            throw new Exception('`terms` is not an array');
        }

        foreach ($terms as $term){
            if(!isset($term['term']) or !isset($term['language'])){
                throw new Exception('`terms` array is malformed.');
            }
        }
    }

    /**
     * @param $glossary
     * @param $type
     * @return false|string
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    private function extractCSVAndValidate($glossary, $type)
    {
        $tmpFileName = tempnam( "/tmp", "MMT_EXCEL_GLOSS_" );

        $objReader = IOFactory::createReaderForFile( $glossary->file_path );

        $objPHPExcel = $objReader->load( $glossary->file_path );
        $objWriter   = new Csv( $objPHPExcel );
        $objWriter->save( $tmpFileName );

        $oldPath             = $glossary->file_path;
        $glossary->file_path = $tmpFileName;

        unlink( $oldPath );

        // validate
        $csv = CSVParser::parse($tmpFileName);

        if(empty($csv)){
            throw new Exception("Glossary is empty");
        }

        if(count($csv[0]) == 2 and $type !== 'unidirectional'){
            throw new Exception("Wrong type: the file type MUST be `unidirectional`");
        }

        if(count($csv[0]) > 2 and $type !== 'equivalent'){
            throw new Exception("Wrong type: the file type MUST be `equivalent`");
        }

        return $glossary->file_path;
    }

    /**
     * @param $engineId
     * @return \Engines_AbstractEngine
     * @throws Exception
     */
    private function getModernMTClient($engineId)
    {
        return EngineValidator::engineBelongsToUser($engineId, $this->user->uid, Engines_MMT::class);
    }

    /**
     * @param $params
     * @param $memory
     * @return bool
     */
    private function filterResult($params, $memory)
    {
        if(isset($params['q'])){
            $q = filter_var($params['q'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW );
            if(false === strpos($memory['name'], $q)){
                return false;
            }
        }

        if(isset($params['has_glossary'])){
            $hasGlossary = filter_var($params['has_glossary'], FILTER_VALIDATE_BOOLEAN);

            if($memory['has_glossary'] != $hasGlossary){
                return false;
            }
        }

        return true;
    }

    /**
     * @param $memory
     * @return array
     */
    private function buildResult($memory)
    {
        return [
            'id' => $memory['id'],
            'name' => $memory['name'],
            'has_glossary' => (isset($memory['has_glossary']) ? $memory['has_glossary'] : false),
        ];
    }
}