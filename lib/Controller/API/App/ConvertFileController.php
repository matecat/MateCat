<?php

namespace API\App;

use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use ConvertFile;
use Exception;
use INIT;
use InvalidArgumentException;

class ConvertFileController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    public function handle()
    {
        try {
            $data = $this->validateTheRequest();
            $cookieDir = $_COOKIE[ 'upload_session' ];
            $intDir    = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $cookieDir;
            $errDir    = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR . 'conversion_errors' . DIRECTORY_SEPARATOR . $cookieDir;

            $this->featureSet->loadFromUserEmail($this->user->email);

            $convertFile = new ConvertFile(
                [$data['file_name']],
                $data['source_lang'],
                $data['target_lang'],
                $intDir,
                $errDir,
                $cookieDir, 
                $data['segmentation_rule'],
                $this->featureSet,
                $data['filters_extraction_parameters'],
                $convertZipFile = true
            );

            $convertFile->convertFiles();
            $convertFileErrors = $convertFile->getErrors();

            if(empty($convertFileErrors)){
                return $this->response->json([
                    'code' => 1,
                    'data' => [],
                    'errors' => [],
                    'warnings' => [],
                ]);
            }

            return $this->response->json($convertFileErrors);

        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return array|\Klein\Response
     * @throws Exception
     */
    private function validateTheRequest(): array
    {
        $file_name = filter_var( $this->request->param( 'file_name' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW ] );
        $source_lang = filter_var( $this->request->param( 'source_lang' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $target_lang = filter_var( $this->request->param( 'target_lang' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $segmentation_rule = filter_var( $this->request->param( 'segmentation_rule' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $filters_extraction_parameters = filter_var( $this->request->param( 'filters_extraction_parameters' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );

        if(empty($file_name)){
            throw new InvalidArgumentException("Missing file name.");
        }

        if(empty($source_lang)){
            throw new InvalidArgumentException("Missing source language.");
        }

        if(empty($target_lang)){
            throw new InvalidArgumentException("Missing target language.");
        }

        if(empty($segmentation_rule)){
            throw new InvalidArgumentException("Missing segmentation rule.");
        }

        return [
            'file_name' => $file_name,
            'source_lang' => $source_lang,
            'target_lang' => $target_lang,
            'segmentation_rule' => $segmentation_rule,
            'filters_extraction_parameters' => $filters_extraction_parameters,
        ];
    }
}