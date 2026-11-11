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
use ZipArchive;

class ZipContentObject extends stdClass
{
    /**
     * Extensions of the files that are already compressed, see addToZip().
     */
    private const array STORED_EXTENSIONS = ['zip', 'docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp', 'pdf', 'png', 'jpg', 'jpeg', 'gif'];

    public string $output_filename;
    public ?string $input_filename = null;
    public ?string $document_content = null;

    /**
     * The path of the file to send when it is on the local disk and its content is not loaded yet,
     * so it can be streamed or zipped from the disk. Null otherwise. Example: an xliff rebuilt in
     * the download folder gives its path; a file kept on S3 gives null.
     */
    public function localPath(): ?string
    {
        if (empty($this->document_content) && !empty($this->input_filename) && is_file($this->input_filename)) {
            return $this->input_filename;
        }

        return null;
    }

    /**
     * Adds this file to $zip as $entryName: from the disk by path when it is there, so it is never
     * read into memory, otherwise from its content.
     *
     * A file in STORED_EXTENSIONS is already compressed and is stored as it is: deflating a 644 MB
     * pptx job again costs 13 to 30 s of CPU inside close(), counted against set_time_limit(180),
     * and saves 1 to 3 percent. Example: `a.docx` is stored, `a.xlf` is deflated.
     *
     * @throws ReflectionException
     * @throws ConnectionException
     * @throws Exception
     * @throws TypeError
     */
    public function addToZip(ZipArchive $zip, string $entryName): void
    {
        $localPath = $this->localPath();
        if ($localPath === null) {
            $zip->addFromString($entryName, $this->getContent() ?? '');

            return;
        }

        $zip->addFile($localPath, $entryName);
        // A regular file readable and writable by all (0666), as addFromString() gives, not the
        // mode of the file on disk. Example: an inner zip rebuilt by tempnam() is 0600.
        // libzip drops a value equal to its own default (exactly 0100666 << 16) and keeps the mode
        // of the file: the MS-DOS archive bit (0x20) makes the value differ, and unzip tools ignore it.
        $zip->setExternalAttributesName($entryName, ZipArchive::OPSYS_UNIX, (0100666 << 16) | 0x20);
        if (in_array(strtolower(pathinfo($entryName, PATHINFO_EXTENSION)), self::STORED_EXTENSIONS, true)) {
            $zip->setCompressionName($entryName, ZipArchive::CM_STORE);
        }
    }

    /**
     * The bytes of the file, read from document_content, the disk or S3. What is read is returned,
     * not kept on the object.
     *
     * @throws ReflectionException
     * @throws ConnectionException
     * @throws Exception
     * @throws TypeError
     */
    public function getContent(): ?string
    {
        if (!empty($this->document_content) || empty($this->input_filename)) {
            return $this->document_content;
        }

        if (AbstractFilesStorage::isOnS3() and false === file_exists($this->input_filename)) {
            return $this->readFromS3($this->input_filename);
        }

        return $this->readFromFileSystem($this->input_filename);
    }

    /**
     * @throws ReflectionException
     * @throws ConnectionException
     * @throws Exception
     */
    private function readFromS3(string $key): ?string
    {
        $s3Client = S3FilesStorage::getStaticS3Client();
        $config = [
            'bucket' => S3FilesStorage::getFilesStorageBucket(),
            'key'    => $key,
        ];

        if (!$s3Client->hasItem($config)) {
            throw new Exception("File: " . $key . " is not present in S3 storage bucket. ");
        }

        return $s3Client->openItem($config);
    }

    /**
     * @throws Exception
     */
    private function readFromFileSystem(string $path): ?string
    {
        if (!is_file($path)) {
            throw new Exception("Error while retrieving input_filename content: " . $path);
        }

        $content = file_get_contents($path);

        return $content !== false ? $content : $this->document_content;
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