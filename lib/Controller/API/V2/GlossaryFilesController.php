<?php
/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 29/08/16
 * Time: 17:12
 */

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Utils\TMS\TMSFile;
use Utils\TMS\TMSService;
use Utils\Validator\Contracts\ValidatorObject;
use Utils\Validator\GlossaryCSVValidator;

class GlossaryFilesController extends KleinController
{

    /**
     * @var Request
     */
    protected Request $request;

    protected string $name;
    protected string $tm_key;

    /**
     * @var TMSService
     */
    protected TMSService $TMService;

    /**
     * @var string
     */
    public string $downloadToken;

    protected function afterConstruct(): void
    {
        $this->TMService = new TMSService();
        $this->appendValidator(new LoginValidator($this));
    }

    protected function validateRequest(): void
    {
        parent::validateRequest();

        $filterArgs = [
                'name'          => [
                        'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                        'flags'  => FILTER_FLAG_STRIP_LOW
                ],
                'tm_key'        => [
                        'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'downloadToken' => [
                        'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ]
        ];

        $postInput = (object)filter_var_array(
                $this->request->params(
                        [
                                'tm_key',
                                'name',
                                'downloadToken'
                        ]
                ),
                $filterArgs
        );

        $this->name          = $postInput->name;
        $this->tm_key        = $postInput->tm_key;
        $this->downloadToken = $postInput->downloadToken;
    }

    /**
     * @throws Exception
     */
    public function check(): void
    {
        $stdResult = $this->TMService->uploadFile();

        // validation on request parameters has been performed by $this->validateRequest
        if (!isset($this->tm_key) or $this->tm_key === "") {
            throw new InvalidArgumentException("`TM key` field is mandatory");
        }

        set_time_limit(600);

        $this->extractCSV($stdResult);

        $results = [];

        foreach ($stdResult as $fileInfo) {
            $glossaryCsvValidator = $this->validateCSVFile($fileInfo->file_path);

            $results[] = [
                    'name'              => $this->name,
                    'tmKey'             => $this->tm_key,
                    'numberOfLanguages' => $glossaryCsvValidator->getNumberOfLanguage($fileInfo->file_path),
            ];
        }

        $this->response->json([
                'results' => $results
        ]);
    }

    /**
     * @throws Exception
     */
    public function import(): void
    {
        $stdResult = $this->TMService->uploadFile();

        // validation on request parameters has been performed by $this->validateRequest
        if (!isset($this->tm_key) or $this->tm_key === "") {
            throw new InvalidArgumentException("`TM key` field is mandatory");
        }

        set_time_limit(600);

        $this->extractCSV($stdResult);

        $uuids = [];

        try {
            foreach ($stdResult as $fileInfo) {
                $glossaryCsvValidator = $this->validateCSVFile($fileInfo->file_path);

                // load it into MyMemory

                $file = new TMSFile(
                        $fileInfo->file_path,
                        $this->tm_key,
                        $this->name
                );

                $this->TMService->addGlossaryInMyMemory($file);

                $uuids[] = [
                        "uuid"              => $file->getUuid(),
                        "name"              => $file->getName(),
                        "numberOfLanguages" => $glossaryCsvValidator->getNumberOfLanguage($fileInfo->file_path)
                ];
            }
        } finally {
            foreach ($stdResult as $_fileInfo) {
                unlink($_fileInfo->file_path);
            }
        }

        if (!$this->response->isLocked()) {
            $this->setSuccessResponse(202, [
                    'uuids' => $uuids
            ]);
        }
    }

    /**
     * @param $file
     *
     * @return GlossaryCSVValidator
     * @throws ValidationError
     * @throws Exception
     */
    private function validateCSVFile($file): GlossaryCSVValidator
    {
        $validator = new GlossaryCSVValidator();
        $validator->validate(ValidatorObject::fromArray([
                'csv' => $file
        ]));

        if (count($validator->getExceptions()) > 0) {
            throw new ValidationError($validator->getExceptions()[ 0 ]);
        }

        return $validator;
    }

    /**
     * @throws Exception
     */
    public function importStatus(): void
    {
        $uuid = $this->params[ 'uuid' ];

        $result = $this->TMService->glossaryUploadStatus($uuid);

        if (!$this->response->isLocked()) {
            $this->setSuccessResponse($result[ 'data' ]->responseStatus, $result[ 'data' ]);
        }
    }

    /**
     * @throws Exception
     */
    public function download(): void {

        if ( !isset( $this->tm_key ) or $this->tm_key === "" ) {
            throw new InvalidArgumentException( "`TM key` field is mandatory" );
        }

        $result = $this->TMService->glossaryExport($this->tm_key, $this->name, $this->getUser()->getEmail(), $this->getUser()->fullName());

        if (!$this->response->isLocked() && in_array($result->responseStatus, [200, 202], true)) {
            $this->setSuccessResponse($result->responseStatus, $result->responseData);
        } else {
            throw new Exception("Error while requesting export", $result->responseStatus);
        }
    }

    protected function setSuccessResponse(int $code = 200, array $data = []): void
    {
        $this->response->code($code);
        $this->response->json([
                'errors'  => [],
                "data"    => $data,
                "success" => true
        ]);
    }

    /**
     * @throws ValidationError
     */
    protected function extractCSV($stdResult)
    {
        $tmpFileName = tempnam("/tmp", "MAT_EXCEL_GLOSS_");

        try {
            //$stdResult in this case handle every time only a file,
            // this cycle is needed to make this method not dependent on the FORM key name
            foreach ($stdResult as $fileInfo) {
                $objReader = IOFactory::createReaderForFile($fileInfo->file_path);

                $objPHPExcel = $objReader->load($fileInfo->file_path);
                $objWriter   = new Csv($objPHPExcel);
                $objWriter->save($tmpFileName);

                $oldPath             = $fileInfo->file_path;
                $fileInfo->file_path = $tmpFileName;

                unlink($oldPath);
            }

            return $stdResult;
        } catch (Exception $e) {
            throw new ValidationError($e->getMessage(), $e->getCode(), $e);
        }
    }

}