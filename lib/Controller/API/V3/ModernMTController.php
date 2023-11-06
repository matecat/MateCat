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
            $engineId = $this->request->engineId;
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

            $glossaryId = filter_var( $_POST['glossaryId' ], FILTER_SANITIZE_NUMBER_INT );
            $type = filter_var( $_POST['type' ], FILTER_SANITIZE_STRING );

            $uploadManager = new Upload();
            $uploadedFiles = $uploadManager->uploadFiles( $_FILES );

            $glossary = $this->extractCSVAndValidate($uploadedFiles->glossary, $type);

            $engineId = $this->request->engineId;
            $MMTClient = $this->getModernMTClient($engineId);

            $this->response->status()->setCode( 200 );
            $this->response->json($MMTClient->importGlossary($glossaryId, [
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
     * @throws Exception
     */
    private function validateImportGlossaryParams()
    {
        // validate params
        if(!isset($_FILES['glossary'])){
            throw new Exception('Missing `glossary` files');
        }

        if(!isset($_POST['glossaryId'])){
            throw new Exception('Missing `glossaryId` param');
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
            $q = filter_var($params['q'], [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW  ] );
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