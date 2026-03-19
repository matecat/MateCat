<?php

namespace Model\ProjectCreation;

use Exception;
use Model\Concerns\LogsMessages;
use Model\ConnectedServices\GDrive\Session;
use Model\ConnectedServices\Oauth\Google\GoogleProvider;
use Model\Files\MetadataDao;
use Model\FilesStorage\AbstractFilesStorage;
use Model\FilesStorage\FilesStorageFactory;
use Psr\Log\LoggerInterface;
use Throwable;
use Utils\Registry\AppConfig;

/**
 * Handles the file-insertion pipeline during project creation.
 *
 * Responsibilities:
 * - Caching native XLIFF files and registering their hashes
 * - Resolving conversion hashes to cached XLIFF paths
 * - Validating cached XLIFF files
 * - Inserting file records into the database
 * - Moving files from cache to final storage
 * - Mapping file-insertion errors to user-friendly messages
 *
 * @see ProjectManager::createProject() — the orchestrator that calls this service
 */
class FileInsertionService
{
    use LogsMessages;

    public function __construct(
        private ProjectManagerModel $projectManagerModel,
        private MetadataDao         $filesMetadataDao,
        private ?Session            $gdriveSession,
        private \Closure            $s3FileDownloader,
        LoggerInterface             $logger,
    ) {
        $this->logger = $logger;
    }

    /**
     * For native XLIFF files that bypassed the conversion service, retroactively
     * create file-system cache entries and register their hashes into the
     * conversionHashes structure.
     *
     * During upload, files that need conversion (DOCX, PDF, etc.) go through
     * {@see \Model\Conversion\ConversionHandler}, which creates cache packages
     * and registers their hashes via
     * {@see AbstractFilesStorage::linkSessionToCacheForOriginalFiles()}.
     * Native XLIFF files skip that pipeline entirely.
     *
     * This method bridges the gap: it caches native XLIFFs via
     * {@see AbstractFilesStorage::makeCachePackage()} (with null original path),
     * links them to the session, and appends their hashes to
     * {@code $linkFiles['conversionHashes']} so they are indistinguishable from
     * converted files in the downstream {@see resolveAndInsertFiles()} pipeline.
     *
     * @param AbstractFilesStorage $fs       The file storage abstraction
     * @param ProjectStructure     $projectStructure Project data
     * @param string               $uploadDir Absolute path to the upload directory
     * @param array<string, mixed> &$linkFiles Modified by reference — native XLIFF
     *     hashes are appended to ['conversionHashes']['sha'] and
     *     ['conversionHashes']['fileName'].
     *
     * @throws Exception
     */
    public function registerNativeXliffsAsConverted(
        AbstractFilesStorage $fs,
        ProjectStructure $projectStructure,
        string $uploadDir,
        array &$linkFiles,
    ): void {
        foreach ($projectStructure->array_files as $pos => $fileName) {
            $meta = $projectStructure->array_files_meta[$pos];

            if ($meta['mustBeConverted']) {
                continue;
            }

            $filePathName = "$uploadDir/$fileName";

            if (AbstractFilesStorage::isOnS3() && false === file_exists($filePathName)) {
                ($this->s3FileDownloader)($fileName);
            }

            $sha1 = sha1_file($filePathName);
            if ($sha1 === false) {
                $this->addProjectError(
                    $projectStructure,
                    ProjectCreationError::FILE_HASH_FAILED->value,
                    "Failed to compute hash for file $fileName"
                );
                continue;
            }

            try {
                $fs->makeCachePackage($sha1, (string)$projectStructure->source_language, null, $filePathName);
                $this->logger->debug("File $fileName converted to cache");
            } catch (Exception $e) {
                $this->addProjectError($projectStructure, ProjectCreationError::FILE_HASH_FAILED->value, $e->getMessage());
            }

            $fs->linkSessionToCacheForAlreadyConvertedFiles(
                $sha1,
                (string)$projectStructure->uploadToken,
                $fileName
            );

            $hashKey = $sha1 . AbstractFilesStorage::OBJECTS_SAFE_DELIMITER . $projectStructure->source_language;
            $linkFiles['conversionHashes']['sha'][] = $hashKey;
            $linkFiles['conversionHashes']['fileName'][$hashKey][] = $fileName;
            $linkFiles['conversionHashes']['sha'] = array_unique($linkFiles['conversionHashes']['sha']);
        }
    }

    /**
     * Resolve conversion hashes, validate cached XLIFF files, and insert
     * file records into the database.
     *
     * Iterates over each entry in {@code $linkFiles['conversionHashes']['sha']},
     * locates the corresponding cached XLIFF, validates it, and inserts file
     * records via {@see ProjectManagerModel::insertFile()}.
     *
     * @param AbstractFilesStorage $fs       The file storage abstraction
     * @param ProjectStructure     $projectStructure Project data (mutable — file_id_list is appended)
     * @param array<string, mixed> $linkFiles Conversion and zip hashes from upload
     *
     * @return array<int, array<string, mixed>> File structures keyed by file ID (fid).
     *
     * @throws FileInsertionException On any failure. User-friendly errors are
     *     already appended to $projectStructure->result['errors'] before throwing.
     *     The caller is responsible for project cleanup and queue management.
     */
    public function resolveAndInsertFiles(
        AbstractFilesStorage $fs,
        ProjectStructure $projectStructure,
        array $linkFiles,
    ): array {
        // Collect the DB/file structure created for all processed files.
        $totalFilesStructure = [];

        // Stop early if there are no converted file hashes to resolve.
        if (!isset($linkFiles['conversionHashes']) || !isset($linkFiles['conversionHashes']['sha'])) {
            return $totalFilesStructure;
        }

        // Process each converted-file reference produced during conversion.
        foreach ($linkFiles['conversionHashes']['sha'] as $linkFile) {
            // Extract the hash+lang token from the path/identifier.
            $hashFile = AbstractFilesStorage::basename_fix($linkFile);
            $hashFile = explode(AbstractFilesStorage::OBJECTS_SAFE_DELIMITER, $hashFile);

            // The first part is the original file hash, the second is the target language.
            $sha1_original = $hashFile[0];
            $lang = $hashFile[1] ?? '';

            // Skip malformed entries with no language suffix.
            if (empty($lang)) {
                continue;
            }

            // Locate the converted XLIFF in cache/storage for this hash+language.
            $cachedXliffFilePathName = $fs->getXliffFromCache($sha1_original, $lang) ?: null;

            // Get the original file names associated with this converted file.
            $_originalFileNames = $linkFiles['conversionHashes']['fileName'][$linkFile];

            try {
                // Ensure original names exist and the cached converted file is valid.
                $this->validateCachedXliff($cachedXliffFilePathName, $_originalFileNames, $linkFiles);

                // Insert file records using the original names and resolved XLIFF path.
                $filesStructure = $this->insertFiles($projectStructure, $_originalFileNames, $sha1_original, (string)$cachedXliffFilePathName);

                // Treat "nothing inserted" as a hard failure.
                if (count($filesStructure ?: []) === 0) {
                    $this->logger->error('No files inserted in DB', [$_originalFileNames, $sha1_original, $cachedXliffFilePathName]);
                    throw new Exception('Files could not be saved in database.', ProjectCreationError::FILE_NOT_FOUND->value);
                }
            } catch (Throwable $e) {
                // Format the error into user-friendly messages and rethrow.
                $this->mapFileInsertionError($projectStructure, $e);
                throw new FileInsertionException($e->getMessage(), $e->getCode(), $e);
            }

            // Merge inserted file info into the overall result.
            // Note: += is intentional here — array_merge() would re-index the
            // numeric keys, losing the $fid mapping that downstream consumers
            // rely on. Key collisions cannot occur because $fid values are
            // database auto-increment IDs, guaranteed unique across all
            // insertFiles() calls within the same project.
            $totalFilesStructure += $filesStructure;
        }

        // Return the combined structure for all successfully inserted files.
        return $totalFilesStructure;
    }

    /**
     * Validate that a cached XLIFF file exists and has a valid extension.
     *
     * @param list<string> $_originalFileNames
     * @param array<string, mixed> $linkFiles
     *
     * @throws Exception
     */
    private function validateCachedXliff(?string $cachedXliffFilePathName, array $_originalFileNames, array $linkFiles): void
    {
        if (count($_originalFileNames ?: []) === 0) {
            $this->logger->error('No hash files found', [$linkFiles['conversionHashes']]);
            throw new Exception('No hash files found', ProjectCreationError::FILE_NOT_FOUND->value);
        }

        if (AbstractFilesStorage::isOnS3()) {
            if (!$cachedXliffFilePathName) {
                throw new Exception(sprintf('Key not found on S3 cache bucket for file %s.', implode(',', $_originalFileNames)), ProjectCreationError::FILE_NOT_FOUND->value);
            }
        } elseif ($cachedXliffFilePathName === null || !file_exists($cachedXliffFilePathName)) {
            throw new Exception(sprintf('File %s not found on server after upload.', $cachedXliffFilePathName), ProjectCreationError::FILE_NOT_FOUND->value);
        }

        $info = AbstractFilesStorage::pathinfo_fix($cachedXliffFilePathName);

        if (!in_array($info['extension'] ?? '', ['xliff', 'sdlxliff', 'xlf'])) {
            throw new Exception("Failed to find converted Xliff", ProjectCreationError::XLIFF_NOT_FOUND->value);
        }
    }

    /**
     * Map file-insertion error codes to user-friendly project errors.
     */
    private function mapFileInsertionError(ProjectStructure $projectStructure, Throwable $e): void
    {
        $code = $e->getCode();

        match (true) {
            $code == ProjectCreationError::REFERENCE_FILES_DISK_ERROR->value => $this->addProjectError($projectStructure, $code, "Failed to store reference files on disk. Permission denied"),
            $code == ProjectCreationError::REFERENCE_FILES_DB_ERROR->value => $this->addProjectError($projectStructure, $code, "Failed to store reference files in database"),
            $code == ProjectCreationError::XLIFF_NOT_FOUND->value => $this->addProjectError(
                $projectStructure,
                ProjectCreationError::XLIFF_CONVERSION_NOT_FOUND->value,
                "File not found. Failed to save XLIFF conversion on disk."
            ),
            $code == ProjectCreationError::GENERIC_ERROR->value && str_contains($e->getMessage(), '<Message>Invalid copy source encoding.</Message>') => $this->addProjectError(
                $projectStructure,
                ProjectCreationError::FILE_MOVE_FAILED->value,
                'There was a problem during the upload of your file(s). Please, ' .
                'try to rename your file(s) avoiding non-standard characters'
            ),
            in_array(
                $code,
                [
                    ProjectCreationError::ZIP_STORE_FAILED->value,
                    ProjectCreationError::FILE_NOT_FOUND->value,
                    ProjectCreationError::FILE_CACHE_ERROR->value,
                    ProjectCreationError::FILE_MOVE_FAILED->value,
                    ProjectCreationError::GENERIC_ERROR->value
                ],
                true
            ) => $this->addProjectError($projectStructure, $code, $e->getMessage()),
            default => $this->addProjectError($projectStructure, $code, 'An unexpected error occurred during file insertion: ' . $e->getMessage()),
        };
    }

    /**
     * Insert files into the database, moving them from the cache to the file directory.
     *
     * @param ProjectStructure $projectStructure Project data (mutable — file_id_list is appended)
     * @param list<string> $_originalFileNames
     * @param string $sha1_original e.g. 917f7b03c8f54350fb65387bda25fbada43ff7d8
     * @param string $cachedXliffFilePathName e.g. 91/7f/...!!it-it/work/test_2.txt.sdlxliff
     *
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    private function insertFiles(ProjectStructure $projectStructure, array $_originalFileNames, string $sha1_original, string $cachedXliffFilePathName): array
    {
        $fs = FilesStorageFactory::create();

        $createDate = date_create($projectStructure->create_date);
        if ($createDate === false) {
            throw new Exception('Invalid create_date for project');
        }
        $yearMonthPath = $createDate->format('Ymd');
        $fileDateSha1Path = $yearMonthPath . DIRECTORY_SEPARATOR . $sha1_original;

        //return structure
        $fileStructures = [];

        foreach ($_originalFileNames as $pos => $originalFileName) {
            // avoid blank filenames
            if (!empty($originalFileName)) {
                // get metadata
                $meta = $projectStructure->array_files_meta[$pos] ?? null;
                /** @var string $fileExtension */
                $fileExtension = AbstractFilesStorage::pathinfo_fix($originalFileName, PATHINFO_EXTENSION);
                $fidStr = $this->projectManagerModel->insertFile(
                    (int)$projectStructure->id_project,
                    (string)$projectStructure->source_language,
                    $originalFileName,
                    $fileExtension,
                    $fileDateSha1Path
                );
                $fid = (int)$fidStr;

                if ($this->gdriveSession) {
                    $gdriveFileId = $this->gdriveSession->findFileIdByName($originalFileName);
                    if ($gdriveFileId) {
                        $client = GoogleProvider::getClient(AppConfig::$HTTPHOST . "/gdrive/oauth/response");
                        $this->gdriveSession->createRemoteFile($fid, $gdriveFileId, $client);
                    }
                }

                $moved = $fs->moveFromCacheToFileDir(
                    $fileDateSha1Path,
                    (string)$projectStructure->source_language,
                    $fidStr,
                    $originalFileName
                );

                // check if the files were moved
                if (true !== $moved) {
                    throw new Exception('Project creation failed. Please refresh page and retry.', ProjectCreationError::FILE_MOVE_FAILED->value);
                }

                $projectStructure->file_id_list[] = $fid;

                // pdfAnalysis
                if (!empty($meta['pdfAnalysis'])) {
                    $this->filesMetadataDao->insert((int)$projectStructure->id_project, $fid, 'pdfAnalysis', (string)json_encode($meta['pdfAnalysis']));
                }

                $fileStructures[$fid] = [
                    'fid' => $fid,
                    'original_filename' => $originalFileName,
                    'path_cached_xliff' => $cachedXliffFilePathName,
                    'mime_type' => $fileExtension
                ];
            }
        }

        return $fileStructures;
    }

    /**
     * Append an error entry to projectStructure->result['errors'].
     */
    private function addProjectError(ProjectStructure $projectStructure, int $code, string $message): void
    {
        $projectStructure->result['errors'][] = [
            "code" => $code,
            "message" => $message,
        ];
    }
}
