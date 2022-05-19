<?php

namespace Conversion ;

use Constants\ConversionHandlerStatus;

class ConvertedFileModel implements \JsonSerializable
{
    /**
     * @var int
     */
    private $code;

    /**
     * @var array
     */
    private $errors;

    /**
     * @var array
     */
    private $data;

    /**
     * ConvertFileModel constructor.
     *
     * @param null $code
     *
     * @throws \Exception
     */
    public function __construct($code = null)
    {
        if(empty($code)){
            $this->code = ConversionHandlerStatus::NOT_CONVERTED;
        } else {
            $this->changeCode($code);
        }

        $this->errors = [];
        $this->data = [];
    }

    /**
     * @param $code
     *
     * @return bool
     */
    private function validateCode($code)
    {
        $allowed = [
            ConversionHandlerStatus::ZIP_HANDLING,
            ConversionHandlerStatus::OK ,
            ConversionHandlerStatus::NOT_CONVERTED,
            ConversionHandlerStatus::INVALID_FILE,
            ConversionHandlerStatus::NESTED_ZIP_FILES_NOT_ALLOWED,
            ConversionHandlerStatus::SOURCE_ERROR,
            ConversionHandlerStatus::TARGET_ERROR,
            ConversionHandlerStatus::UPLOAD_ERROR,
            ConversionHandlerStatus::MISCONFIGURATION,
            ConversionHandlerStatus::INVALID_TOKEN,
            ConversionHandlerStatus::OCR_WARNING,
            ConversionHandlerStatus::OCR_ERROR,
            ConversionHandlerStatus::GENERIC_ERROR,
            ConversionHandlerStatus::FILESYSTEM_ERROR,
            ConversionHandlerStatus::S3_ERROR,
        ];

        return in_array($code, $allowed);
    }

    /**
     * @param $code
     *
     * @throws \Exception
     */
    public function changeCode($code)
    {
        if(!$this->validateCode($code)){
            throw new \Exception($code . ' is not a valid code');
        }

        $this->code = $code;
    }

    /**
     * @return int
     */
    public function getCode() {
        return $this->code;
    }

    /**
     * @return bool
     */
    public function hasAnErrorCode()
    {
        return $this->code <= 0;
    }

    /**
     * @param string $messageError
     * @param null $debug
     */
    public function addError($messageError, $debug = null)
    {
        if($debug){
            $this->errors[$debug] = [
                'code' => $this->code,
                'message' => $messageError,
                'debug' => $debug
            ];

        } else {
            $this->errors[] = [
                    'code' => $this->code,
                    'message' => $messageError,
            ];
        }

    }

    /**
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * @return array
     */
    public function getData() {
        return $this->data;
    }

    /**
     * @param $key
     * @param $value
     */
    public function addData( $key, $value )
    {
        $this->data[$key] = $value;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'code' => $this->getCode(),
            'errors' => $this->getErrors(),
            'data' => $this->getData(),
        ];
    }
}