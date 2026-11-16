<?php

namespace Controller\Abstracts;

use Controller\Abstracts\Authentication\CookieManager;
use Controller\Exceptions\RenderTerminatedException;
use Controller\Traits\KleinResponseFileStream;
use Exception;
use Model\Files\FileDao;
use Model\FilesStorage\AbstractFilesStorage;
use Model\FilesStorage\FilesStorageFactory;
use Model\FilesStorage\S3FilesStorage;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Predis\Connection\ConnectionException;
use ReflectionException;
use TypeError;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;
use View\API\Commons\ZipContentObject;
use ZipArchive;

abstract class AbstractDownloadController extends AbstractStatefulKleinController
{
    /**
     * Age, in seconds, after which getWorkDir() deletes a work folder as abandoned. 12 hours.
     */
    public const int WORK_DIR_MAX_AGE = 43200;

    public int $id_job;
    public string $password;

    /**
     * The file finalize() sends, streamed from the disk so it is never held in memory.
     */
    protected ?string $outputFile = null;

    /**
     * The folder this request writes its temporary files to, see getWorkDir().
     */
    private ?string $workDir = null;

    protected string $_filename = "";

    protected static string $ZIP_ARCHIVE = "application/zip";
    protected static string $XLIFF_FILE = "application/xliff+xml";
    protected static string $OCTET_STREAM = "application/octet-stream";
    protected string $mimeType = "application/octet-stream";


    protected ?string $_user_provided_filename = null;

    protected JobStruct $job;

    protected ProjectStruct $project;

    private ?ProjectDao $projectDao = null;

    private function getProjectDao(): ProjectDao
    {
        return $this->projectDao ??= new ProjectDao($this->getDatabase());
    }

    /**
     * @param int $ttl
     *
     * @return JobStruct
     * @throws ReflectionException
     * @throws Exception
     */
    public function getJob(int $ttl = 0): JobStruct
    {
        if (empty($this->job)) {
            $this->job = (new JobDao($this->getDatabase()))->getNotDeletedById($this->id_job, $ttl)[0];
        }

        return $this->job;
    }

    /**
     * Sets the file finalize() sends. A file on the local disk is sent as it is; any other content
     * is written to the work folder first. Example: a zip in the work folder is sent from there.
     *
     * @return $this
     * @throws ReflectionException
     * @throws ConnectionException
     * @throws Exception
     * @throws TypeError
     */
    public function setOutputContent(ZipContentObject $content): AbstractDownloadController
    {
        $this->outputFile = $content->localPath();
        if ($this->outputFile === null) {
            $this->outputFile = $this->getWorkDir() . DIRECTORY_SEPARATOR . 'output';
            if (file_put_contents($this->outputFile, $content->getContent() ?? '') === false) {
                throw new Exception('Unable to write the download file ' . $this->outputFile);
            }
        }

        return $this;
    }

    /**
     * The folder where this request keeps its temporary files: the XLIFFs it rebuilds, the
     * converted documents and the zip it sends. One folder per request, so two downloads of the
     * same job never delete each other's files. Example: `<TMP_DOWNLOAD>/12345/6731f0c2a1b3e/`.
     *
     * Who deletes it: finalize() calls deleteWorkDir() just before the file goes out, because a
     * client that cancels the download stops PHP inside the stream, and `exit` skips every
     * `finally`. A download that fails, or is not sent (GDrive), is cleaned by the `finally` of the
     * controller action. A request killed by a fatal error or a timeout runs neither: a later
     * getWorkDir() deletes its folder once it is older than WORK_DIR_MAX_AGE.
     *
     * @throws Exception
     */
    public function getWorkDir(): string
    {
        if ($this->workDir === null) {
            self::deleteAbandonedWorkDirs();

            $workDir = AppConfig::$TMP_DOWNLOAD . DIRECTORY_SEPARATOR . $this->id_job . DIRECTORY_SEPARATOR . uniqid('', true);
            // A second try: another download that deletes its own folder also deletes the job
            // folder when it looks empty, and that can happen between the two levels created here.
            if (!mkdir($workDir, 0700, true) && !mkdir($workDir, 0700, true) && !is_dir($workDir)) {
                throw new Exception('Unable to create the download folder ' . $workDir);
            }
            $this->workDir = $workDir;
        }

        return $this->workDir;
    }

    /**
     * Deletes the folder of getWorkDir() with everything in it. A second call does nothing.
     */
    protected function deleteWorkDir(): void
    {
        if ($this->workDir === null) {
            return;
        }

        self::deleteWorkDirAndEmptyJobDir($this->workDir);
        $this->workDir = null;
    }

    /**
     * Deletes the work folders, of any job, older than WORK_DIR_MAX_AGE. Example:
     * `<TMP_DOWNLOAD>/12345/6731f0c2a1b3e/` last changed 13 hours ago is removed, one changed
     * 5 minutes ago is in use and stays.
     */
    private static function deleteAbandonedWorkDirs(): void
    {
        $expired = time() - self::WORK_DIR_MAX_AGE;

        foreach (glob(AppConfig::$TMP_DOWNLOAD . '/*/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $changed = filemtime($dir);
            if ($changed !== false && $changed < $expired) {
                self::deleteWorkDirAndEmptyJobDir($dir);
            }
        }
    }

    /**
     * Deletes a work folder, then its job folder when no other download uses it. A folder that
     * cannot be deleted is logged: it must not turn a sent download into an error, nor hide the
     * error of a failed one.
     */
    private static function deleteWorkDirAndEmptyJobDir(string $dir): void
    {
        try {
            Utils::deleteDir($dir);
        } catch (Exception $e) {
            LoggerFactory::getLogger('conversion')->warning('Failed to delete the download folder ' . $dir . ': ' . $e->getMessage());
        }

        @rmdir(dirname($dir));
    }

    /**
     * The object that writes a file to the response. A method so a test can see what is sent.
     */
    protected function createFileStream(): KleinResponseFileStream
    {
        return new KleinResponseFileStream($this->response);
    }

    /**
     * The storage that holds the job files. A method so a test can give its own files.
     * Example: `FsFilesStorage` when AppConfig::$FILE_STORAGE_METHOD is 'fs'.
     *
     * @throws Exception
     * @throws TypeError
     */
    protected function getFilesStorage(): AbstractFilesStorage
    {
        return FilesStorageFactory::create();
    }

    /**
     * Copies a file from the S3 bucket of the file storage to the local disk. A method so a test
     * can answer for S3. Example: `downloadFromS3('files/20261110/7/a.docx', $workDir . '/a.docx')`.
     *
     * @throws ReflectionException
     * @throws Exception
     */
    protected function downloadFromS3(string $key, string $saveAs): void
    {
        S3FilesStorage::getStaticS3Client()->downloadItem([
            'bucket' => S3FilesStorage::getFilesStorageBucket(),
            'key' => $key,
            'save_as' => $saveAs,
        ]);
    }

    /**
     * Ends the request once the response is sent. Under the testing env it throws instead of
     * calling exit, so the path can be tested.
     *
     * @throws RenderTerminatedException
     */
    protected function endRequest(): never
    {
        AppConfig::$ENV === 'testing' ? throw new RenderTerminatedException() : exit;
    }

    protected function setMimeType(): void
    {
        // One option gives one value: the extension, as a string.
        /** @var string $extension */
        $extension = AbstractFilesStorage::pathinfo_fix($this->_filename, PATHINFO_EXTENSION);

        switch (strtolower($extension)) {
            case "xlf":
            case "sdlxliff":
            case "xliff":
                $this->mimeType = self::$XLIFF_FILE;
                break;
            case "zip":
                $this->mimeType = self::$ZIP_ARCHIVE;
                break;
            default:
                $this->mimeType = self::$OCTET_STREAM;
                break;
        }
    }

    /**
     * @param string $filename
     *
     * @return $this
     */
    public function setFilename(string $filename): AbstractDownloadController
    {
        $this->_filename = $filename;

        return $this;
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->_filename;
    }

    /**
     * @return ProjectStruct
     */
    public function getProject(): ProjectStruct
    {
        return $this->project;
    }

    /**
     * @param array<string, mixed>|null $tokenContent
     */
    protected function unlockToken(?array $tokenContent = null): void
    {
        if (!empty($this->downloadToken)) {
            $cookieValue = json_encode(
                empty($tokenContent)
                    ? ["code" => 0, "message" => "Download complete."]
                    : $tokenContent
            );

            if ($cookieValue === false) {
                return;
            }

            (new CookieManager())->set(
                $this->downloadToken,
                $cookieValue,
                time() + 600,
                true,
                false,
                'Strict'
            );
            $this->downloadToken = null;
        }
    }

    protected function nocache(): void
    {
        $this->createFileStream()->noCache();
    }

    /**
     * Sends $outputFile and ends the request. A GDrive project gets no file, unless the xliff is
     * forced: its files were uploaded to Google Drive.
     *
     * @throws Exception
     */
    public function finalize(bool $forceXliff = false): void
    {
        $this->unlockToken();

        if (empty($this->project)) {
            $this->project = $this->getProjectDao()->findByJobId($this->id_job)
                ?? throw new Exception('Project not found for job ' . $this->id_job);
        }

        if (empty($this->_filename)) {
            $this->_filename = $this->getDefaultFileName($this->project);
        }

        $projectId = $this->project->id ?? throw new Exception('Project not found');
        $isGDriveProject = $this->getProjectDao()->isGDriveProject((int)$projectId);
        if ($isGDriveProject && !$forceXliff) {
            return;
        }

        ob_get_clean();
        $this->streamOutputFile();
    }

    /**
     * Sends $outputFile in chunks, with no output buffer, so the memory used does not grow with the
     * file. No gzip: most files sent are archives or office documents, already compressed.
     *
     * @throws Exception
     */
    private function streamOutputFile(): never
    {
        if ($this->outputFile === null) {
            throw new Exception('Unable to open the download file');
        }

        $filePointer = fopen($this->outputFile, 'rb');
        if ($filePointer === false) {
            throw new Exception('Unable to open the download file ' . $this->outputFile);
        }

        // The open handle still reads the file once its folder is gone.
        $this->deleteWorkDir();

        $this->createFileStream()->streamFileFromPointer(
            $filePointer,
            $this->mimeType,
            'attachment',
            $this->_filename
        );

        $this->endRequest();
    }

    /**
     * @param ProjectStruct $project
     *
     * @return string
     * @throws ReflectionException
     * @throws Exception
     */
    public function getDefaultFileName(ProjectStruct $project): string
    {
        $files = (new FileDao($this->getDatabase()))->getByProjectId((int)$project->id);

        if (count($files) > 1) {
            return $this->project->name . ".zip";
        } else {
            return $files[0]->filename;
        }
    }

    /**
     * Writes the zip of $output_content to $outputFile and returns that path. An empty file is left
     * out. Example: a 600 MB job is zipped from the disk with a few MB of memory.
     *
     * @param ZipContentObject[] $output_content
     *
     * @throws ReflectionException
     * @throws ConnectionException
     * @throws Exception
     * @throws TypeError
     */
    protected static function composeZip(array $output_content, string $outputFile, bool $isOriginalFile = false): string
    {
        $zip = new ZipArchive();
        if ($zip->open($outputFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Unable to create the zip file ' . $outputFile);
        }

        $rev_index_name = [];
        $rev_index_duplicate_count = [];

        foreach ($output_content as $f) {
            $fName = $f->output_filename;

            if (!$isOriginalFile) {
                $fName = self::forceOcrExtension($fName);
            }

            // avoid collisions
            if (array_key_exists($fName, $rev_index_name)) {
                $rev_index_duplicate_count[$fName] = isset($rev_index_duplicate_count[$fName]) ? $rev_index_duplicate_count[$fName] + 1 : 1;

                $fName = $fName . "(" . $rev_index_duplicate_count[$fName] . ")";
            }

            $rev_index_name[$fName] = $fName;

            $localPath = $f->localPath();
            if ($localPath !== null ? filesize($localPath) === 0 : empty($f->getContent())) {
                continue;
            }

            $f->addToZip($zip, $fName);
        }

        self::closeZip($zip, $outputFile);

        // close() writes no file for an archive with no entries.
        if (!is_file($outputFile)) {
            throw new Exception('Unable to write the zip file ' . $outputFile . ': every file is empty');
        }

        return $outputFile;
    }

    /**
     * Closes $zip and fails when the archive cannot be written. libzip reads every file added by
     * path only now, in chunks: their errors show up here. $name only names the archive in the error.
     *
     * @throws Exception
     */
    public static function closeZip(ZipArchive $zip, string $name): void
    {
        if ($zip->close() !== true) {
            throw new Exception('Unable to write the zip file ' . $name . ': ' . $zip->getStatusString());
        }
    }

    /**
     * Removes characters that could break out of the quoted Content-Disposition
     * filename value (double quote, backslash, CR, LF, NUL) — prevents header
     * injection / disposition-parameter smuggling via user-controlled filenames.
     *
     * @param string $filename
     *
     * @return string
     */
    public static function sanitizeContentDispositionFilename(string $filename): string
    {
        return str_replace(['"', '\\', "\r", "\n", "\0"], '', $filename);
    }

    /**
     * @param string $filename
     *
     * @return string
     */
    public static function forceOcrExtension(string $filename): string
    {
        // With no option every field is there.
        /** @var array{dirname: string, basename: string, extension: string, filename: string} $pathinfo */
        $pathinfo = AbstractFilesStorage::pathinfo_fix($filename);

        switch (strtolower($pathinfo['extension'])) {
            case 'pdf':
            case 'bmp':
            case 'png':
            case 'gif':
            case 'jpg':
            case 'jpeg':
            case 'tiff':
            case 'tif':
                $filename = $pathinfo['basename'] . ".docx";
                break;
        }

        return $filename;
    }
}
