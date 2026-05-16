<?php

namespace Controller\Abstracts;

use Controller\Abstracts\Authentication\CookieManager;
use Exception;
use Model\Files\FileDao;
use Model\FilesStorage\AbstractFilesStorage;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use ReflectionException;
use Utils\Registry\AppConfig;
use View\API\Commons\ZipContentObject;
use ZipArchive;

abstract class AbstractDownloadController extends AbstractStatefulKleinController
{
    public int $id_job;
    public string $password;

    protected string $outputContent = "";
    protected string $_filename = "";

    protected static string $ZIP_ARCHIVE = "application/zip";
    protected static string $XLIFF_FILE = "application/xliff+xml";
    protected static string $OCTET_STREAM = "application/octet-stream";
    protected string $mimeType = "application/octet-stream";


    protected ?string $_user_provided_filename = null;

    protected JobStruct $job;

    protected ProjectStruct $project;

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
            $this->job = (new JobDao())->getNotDeletedById($this->id_job, $ttl)[0];
        }

        return $this->job;
    }

    /**
     * @param ZipContentObject $content
     *
     * @return $this
     * @throws Exception
     */
    public function setOutputContent(ZipContentObject $content): AbstractDownloadController
    {
        $this->outputContent = $content->getContent();

        return $this;
    }

    protected function setMimeType(): void
    {
        $extension = AbstractFilesStorage::pathinfo_fix($this->_filename, PATHINFO_EXTENSION);

        if (!is_string($extension)) {
            $this->mimeType = self::$OCTET_STREAM;

            return;
        }

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

            CookieManager::setCookie(
                $this->downloadToken,
                $cookieValue,
                [
                    'expires' => time() + 600,
                    'path' => '/',
                    'domain' => AppConfig::$COOKIE_DOMAIN,
                    'secure' => true,
                    'httponly' => false,
                    'samesite' => 'None',
                ]
            );
            $this->downloadToken = null;
        }
    }

    protected function nocache(): void
    {
        header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    /**
     * @param bool $forceXliff
     *
     * @throws Exception
     */
    public function finalize(bool $forceXliff = false): void
    {
        try {
            $this->unlockToken();

            if (empty($this->project)) {
                $this->project = ProjectDao::findByJobId($this->id_job)
                    ?? throw new Exception('Project not found for job ' . $this->id_job);
            }

            if (empty($this->_filename)) {
                $this->_filename = $this->getDefaultFileName($this->project);
            }

            $projectId = $this->project->id ?? throw new Exception('Project not found');
            $isGDriveProject = ProjectDao::isGDriveProject((int)$projectId);

            if (!$isGDriveProject || $forceXliff === true) {
                ob_get_contents();
                ob_get_clean();
                ob_start("ob_gzhandler");  // compress page before sending
                $this->nocache();

                header("Content-Type: $this->mimeType");
                header(
                    "Content-Disposition: attachment; filename=\"$this->_filename\""
                ); // enclose file name in double quotes in order to avoid duplicate header error. Reference https://github.com/prior/prawnto/pull/16
                header("Expires: 0");
                header("Connection: close");
                header("Content-Length: " . strlen($this->outputContent));
                echo $this->outputContent;
                exit;
            }
        } catch (Exception $e) {
            echo "<pre>";
            print_r($e);
            echo "\n\n\n";
            echo "</pre>";
            exit;
        }
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
        $files = FileDao::getByProjectId((int)$project->id);

        if (count($files) > 1) {
            return $this->project->name . ".zip";
        } else {
            return $files[0]->filename;
        }
    }

    /**
     * @param ZipContentObject[] $output_content
     * @param string|null $outputFile
     * @param ?bool $isOriginalFile
     *
     * @return string
     * @throws Exception
     */
    protected static function composeZip(array $output_content, ?string $outputFile = null, ?bool $isOriginalFile = false): string
    {
        if (empty($outputFile)) {
            $outputFile = tempnam("/tmp", "zipmatecat");
        }

        $zip = new ZipArchive();
        $zip->open($outputFile, ZipArchive::OVERWRITE);

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

            $content = $f->getContent();
            if (!empty($content)) {
                $zip->addFromString($fName, $content);
            }
        }

        // Close and send to users
        $zip->close();
        $zip_content = file_get_contents($outputFile);
        unlink($outputFile);

        if ($zip_content === false) {
            throw new Exception('Failed to read zip file: ' . $outputFile);
        }

        return $zip_content;
    }

    /**
     * @param string $filename
     *
     * @return string
     */
    public static function forceOcrExtension(string $filename): string
    {
        $pathinfo = AbstractFilesStorage::pathinfo_fix($filename);

        if (!is_array($pathinfo) || !isset($pathinfo['extension'], $pathinfo['basename'])) {
            return $filename;
        }

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
