<?php

namespace View\API\Commons;

use Exception;
use Model\DataAccess\UnknownPropertyException;
use Model\FilesStorage\AbstractFilesStorage;
use Model\FilesStorage\S3FilesStorage;
use Predis\Connection\ConnectionException;
use ReflectionException;
use stdClass;
use TypeError;

class ZipContentObject extends stdClass
{

    public string $output_filename;
    public ?string $input_filename = null;
    public ?string $document_content = null;

    /**
     * @throws ReflectionException
     * @throws ConnectionException
     * @throws Exception
     * @throws TypeError
     */
    public function getContent(): ?string
    {
        if (!empty($this->document_content)) {
            return $this->document_content;
        }

        if (!empty($this->input_filename)) {
            if (AbstractFilesStorage::isOnS3() and false === file_exists($this->input_filename)) {
                $this->setDocumentContentFromS3();
            } else {
                $this->setDocumentContentFromFileSystem();
            }
        }

        return $this->document_content;
    }

    /**
     * @throws ReflectionException
     * @throws ConnectionException
     * @throws Exception
     * @throws TypeError
     */
    private function setDocumentContentFromS3(): void
    {
        $s3Client = S3FilesStorage::getStaticS3Client();
        $config = [
            'bucket' => S3FilesStorage::getFilesStorageBucket(),
            'key'    => $this->input_filename,
        ];

        if ($s3Client->hasItem($config)) {
            $this->document_content = $s3Client->openItem($config);
        } else {
            throw new Exception("File: " . $this->input_filename . " is not present in S3 storage bucket. ");
        }
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    private function setDocumentContentFromFileSystem(): void
    {
        if ($this->input_filename !== null && is_file($this->input_filename)) {
            $content = file_get_contents($this->input_filename);
            if ($content !== false) {
                $this->document_content = $content;
            }
        } else {
            throw new Exception("Error while retrieving input_filename content: " . $this->input_filename);
        }
    }

    /**
     * @param array<string, mixed>|ZipContentObject $_array_params
     */
    public function __construct(ZipContentObject|array $_array_params = [])
    {
        if (is_array($_array_params) and isset($_array_params[0])) {
            foreach ($_array_params as $array_params) {
                $this->build($array_params);
            }
        } else {
            $this->build($_array_params);
        }
    }

    /**
     * @param array<string, mixed>|ZipContentObject $_array_params
     */
    public function build(ZipContentObject|array $_array_params = []): void
    {
        if (is_array($_array_params) and isset($_array_params[0])) {
            foreach ($_array_params as $array_params) {
                $this->build($array_params);
            }
        } elseif (!empty($_array_params)) {
            foreach ((array) $_array_params as $property => $value) {
                $this->$property = $value;
            }
        }
    }

    public function __set(string $name, mixed $value): void
    {
        if (!property_exists($this, $name)) {
            throw new UnknownPropertyException($name);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return (array)$this;
    }

}