<?php

namespace API\V3;

use API\V2\KleinController;
use API\V2\Validators\LoginValidator;
use Engines_DeepL;
use Exception;
use Files\CSV as CSVParser;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Upload;
use Validator\EngineValidator;

class DeepLGlossaryController extends KleinController
{
    protected function afterConstruct() {
        parent::afterConstruct();
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * Get all glossaries
     */
    public function all()
    {
        try {
            $engineId = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );
            $deepLClient = $this->getDeepLClient($engineId);

            $deepLApiKey = $deepLClient->getEngineRecord()->extra_parameters['DeepL-Auth-Key'];
            $deepLClient->setApiKey($deepLApiKey);

            $this->response->status()->setCode( 200 );
            $this->response->json($deepLClient->glossaries());
            exit();

        } catch (Exception $exception){
            $code = ($exception->getCode() > 0) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
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
     */
    public function create()
    {
        try {
            $this->validateCreateGlossaryPayload();

            $name = filter_var( $_POST['name'], FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_LOW|FILTER_FLAG_STRIP_HIGH );

            $uploadManager = new Upload();
            $uploadedFiles = $uploadManager->uploadFiles( $_FILES );

            $glossary = CSVParser::extract($uploadedFiles->glossary, "DEEPL_EXCEL_GLOSS_");

            // validate
            $csvHeaders = CSVParser::parseToArray($glossary)[0];
            $csv = CSVParser::withoutHeaders($glossary);

            if(count($csvHeaders) !== 2){
                throw new Exception("Glossary has more or less than 2 columns. Only bilingual files are supported", 400);
            }

            if(empty($csv)){
                throw new Exception("Glossary is empty", 400);
            }

            $data = [
                "name" => $name,
                "source_lang" => $csvHeaders[0],
                "target_lang" => $csvHeaders[1],
                "entries" => $csv,
                "entries_format" => "csv",
            ];

            $engineId = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );
            $deepLClient = $this->getDeepLClient($engineId);

            $deepLApiKey = $deepLClient->getEngineRecord()->extra_parameters['DeepL-Auth-Key'];
            $deepLClient->setApiKey($deepLApiKey);

            $this->response->status()->setCode( 200 );
            $this->response->json($deepLClient->createGlossary($data));
            exit();

        } catch (Exception $exception){
            $code = ($exception->getCode() > 0) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    /**
     * @throws Exception
     */
    private function validateCreateGlossaryPayload()
    {
        if(!isset($_FILES['glossary'])){
            throw new Exception('Missing `glossary`', 400);
        }

        if(!isset($_POST['name'])){
            throw new Exception('Missing `name`', 400);
        }
    }

    /**
     * Delete a single glossary item
     */
    public function delete()
    {
        try {
            $id = filter_var( $this->request->id, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH|FILTER_FLAG_ENCODE_LOW );
            $engineId = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );
            $deepLClient = $this->getDeepLClient($engineId);

            $deepLApiKey = $deepLClient->getEngineRecord()->extra_parameters['DeepL-Auth-Key'];
            $deepLClient->setApiKey($deepLApiKey);

            $this->response->status()->setCode( 200 );
            $deepLClient->deleteGlossary($id);
            $this->response->json([
                'id' => $id
            ]);
            exit();

        } catch (Exception $exception){
            $code = ($exception->getCode() > 0) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    /**
     * Get a single glossary item
     */
    public function get()
    {
        try {
            $id = filter_var( $this->request->id, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH|FILTER_FLAG_ENCODE_LOW );
            $engineId = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );
            $deepLClient = $this->getDeepLClient($engineId);

            $deepLApiKey = $deepLClient->getEngineRecord()->extra_parameters['DeepL-Auth-Key'];
            $deepLClient->setApiKey($deepLApiKey);

            $this->response->status()->setCode( 200 );
            $this->response->json($deepLClient->getGlossary($id));
            exit();

        } catch (Exception $exception){
            $code = ($exception->getCode() > 0) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    /**
     * Get a single glossary item entries
     */
    public function getEntries()
    {
        try {
            $id = filter_var( $this->request->id, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH|FILTER_FLAG_ENCODE_LOW );
            $engineId = filter_var( $this->request->engineId, FILTER_SANITIZE_NUMBER_INT );
            $deepLClient = $this->getDeepLClient($engineId);

            $deepLApiKey = $deepLClient->getEngineRecord()->extra_parameters['DeepL-Auth-Key'];
            $deepLClient->setApiKey($deepLApiKey);

            $this->response->status()->setCode( 200 );
            $this->response->json($deepLClient->getGlossaryEntries($id));
            exit();

        } catch (Exception $exception){
            $code = ($exception->getCode() > 0) ? $exception->getCode() : 500;
            $this->response->status()->setCode( $code );
            $this->response->json([
                'error' => $exception->getMessage()
            ]);
            exit();
        }
    }

    /**
     * @param $glossary
     * @param $type
     * @return false|string
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    private function extractCSV($glossary)
    {
        $tmpFileName = tempnam( "/tmp", "DEEPL_EXCEL_GLOSS_" );

        $objReader = IOFactory::createReaderForFile( $glossary->file_path );

        $objPHPExcel = $objReader->load( $glossary->file_path );
        $objWriter   = new Csv( $objPHPExcel );
        $objWriter->save( $tmpFileName );

        $oldPath             = $glossary->file_path;
        $glossary->file_path = $tmpFileName;

        unlink( $oldPath );

        return $glossary->file_path;
    }

    /**
     * @param $engineId
     * @return \Engines_AbstractEngine
     * @throws Exception
     */
    private function getDeepLClient($engineId)
    {
        $engine = EngineValidator::engineBelongsToUser($engineId, $this->user->uid, Engines_DeepL::class);
        $extraParams = $engine->getEngineRecord()->extra_parameters;

        if(!isset($extraParams['DeepL-Auth-Key'])){
            throw new Exception('`DeepL-Auth-Key` is not set');
        }

        return $engine;
    }
}