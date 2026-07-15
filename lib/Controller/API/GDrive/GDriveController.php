<?php

namespace Controller\API\GDrive;

use Aws\S3\Exception\S3Exception;
use Controller\Abstracts\AbstractStatefulKleinController;
use Controller\Abstracts\Authentication\CookieManager;
use Controller\Exceptions\RenderTerminatedException;
use Exception;
use Google_Service_Exception;
use InvalidArgumentException;
use Klein\Exceptions\LockedResponseException;
use Klein\Exceptions\ResponseAlreadySentException;
use Matecat\Locales\InvalidLanguageException;
use Matecat\Locales\Languages;
use Model\ConnectedServices\GDrive\Session;
use Model\ConnectedServices\Oauth\Google\GoogleProvider;
use Model\Filters\FiltersConfigTemplateDao;
use Model\Filters\FiltersConfigTemplateStruct;
use TypeError;
use Utils\Constants\Constants;
use Utils\Constants\ConversionHandlerStatus;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;

class GDriveController extends AbstractStatefulKleinController
{

    const string GDRIVE_LIST_COOKIE_NAME = 'gdrive_files_to_be_listed';
    const string GDRIVE_OUTCOME_COOKIE_NAME = 'gdrive_files_outcome';

    private string $source_lang = Constants::DEFAULT_SOURCE_LANG;
    private ?FiltersConfigTemplateStruct $filters_extraction_parameters = null;
    private bool $isAsyncReq = true;
    private bool $isImportingSuccessful = true;

    /**
     * @var Session
     */
    private Session $gdriveUserSession;

    /**
     * @var array<string, mixed>
     */
    private array $error = [];

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function open(): void
    {
        $filtersTemplateString = filter_var($this->request->param('filters_extraction_parameters_template'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $filtersTemplateId = filter_var($this->request->param('filters_extraction_parameters_template_id'), FILTER_VALIDATE_INT);
        $this->isAsyncReq = filter_var($this->request->param('isAsync'), FILTER_VALIDATE_BOOLEAN);

        try {
            $segmentation_rule = Constants::validateSegmentationRules($this->request->param('segmentation_rule'));

            $this->source_lang = $this->getValidSourceLanguage();
            $target_lang = $this->getValidTargetLanguages();

            if (!empty($filtersTemplateString)) {
                $filtersTemplate = new FiltersConfigTemplateStruct();
                $filtersTemplate->hydrateFromJSON(html_entity_decode($filtersTemplateString));

                $this->filters_extraction_parameters = $filtersTemplate;
            } elseif (!empty($filtersTemplateId)) {
                $uid = $this->getUser()->uid ?? throw new TypeError('User uid is required');
                $filtersTemplate = (new FiltersConfigTemplateDao($this->getDatabase()))->getByIdAndUser($filtersTemplateId, $uid);

                if (empty($filtersTemplate)) {
                    throw new Exception("filters_extraction_parameters_template_id not valid");
                }

                $this->filters_extraction_parameters = $filtersTemplate;
            }

            $state = json_decode($this->request->param('state'), true);

            if (array_key_exists('ids', $state)) {
                $listOfIds = $state['ids'];
            } elseif (array_key_exists('exportIds', $state)) {
                $listOfIds = $state['exportIds'];
            } else {
                throw new Exception(" no ids or export ids found ");
            }

            // set the upload directory name if there are files from gDrive
            if (!$this->isAsyncReq) {
                $guid = Utils::uuid4();
                (new CookieManager())->set(Constants::COOKIE_UPLOAD_TOKEN, $guid, time() + 86400);
                $_SESSION[Constants::COOKIE_UPLOAD_TOKEN] = $_COOKIE[Constants::COOKIE_UPLOAD_TOKEN] = $guid;
                $this->gdriveUserSession->clearFileListFromSession();
            }

            $guid = $_SESSION[Constants::COOKIE_UPLOAD_TOKEN] = $_COOKIE[Constants::COOKIE_UPLOAD_TOKEN];

            if (!Utils::isTokenValid($guid)) {
                throw new InvalidArgumentException("Invalid Upload Token.", ConversionHandlerStatus::INVALID_TOKEN);
            }

            $this->gdriveUserSession->setConversionParams(
                $guid,
                $this->source_lang,
                $target_lang,
                $segmentation_rule,
                $this->filters_extraction_parameters
            );

            $this->doImport($listOfIds);
            $_SESSION[Constants::SESSION_ACTUAL_SOURCE_LANG] = $this->source_lang;

            $this->finalize();
        } catch (Exception $e) {
            $this->isImportingSuccessful = false;
            $this->error = [
                'code' => $e->getCode(),
                'class' => get_class($e),
                'msg' => $this->getExceptionMessage($e)
            ];

            return;
        }
    }

    /**
     * @return string
     * @throws InvalidLanguageException
     */
    private function getValidSourceLanguage(): string
    {
        $sLang = null;
        $languageHandler = Languages::getInstance();

        if (!empty($this->request->param('target'))) {
            $sLang = $languageHandler->validateLanguageListAsString($this->request->param('source'));
        } elseif (!empty($_COOKIE[Constants::COOKIE_SOURCE_LANG]) && Constants::EMPTY_VAL !== $_COOKIE[Constants::COOKIE_SOURCE_LANG]) {
            $sLang = $languageHandler->validateLanguageListAsString($_COOKIE[Constants::COOKIE_SOURCE_LANG]);
        }

        return $sLang ?? Constants::DEFAULT_SOURCE_LANG;
    }

    /**
     * @return string
     * @throws InvalidLanguageException
     */
    private function getValidTargetLanguages(): string
    {
        $tLang = null;
        $languageHandler = Languages::getInstance();

        if (!empty($this->request->param('target'))) {
            $tLang = $languageHandler->validateLanguageListAsString($this->request->param('target'));
        } elseif (!empty($_COOKIE[Constants::COOKIE_TARGET_LANG]) && Constants::EMPTY_VAL !== $_COOKIE[Constants::COOKIE_TARGET_LANG]) {
            $tLang = $languageHandler->validateLanguageListAsString($_COOKIE[Constants::COOKIE_TARGET_LANG], '||');
        }

        return $tLang ?? Constants::DEFAULT_TARGET_LANG;
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    private function initSessionService(): void
    {
        $this->gdriveUserSession = new Session($this->getDatabase());
    }

    /**
     * @param list<string> $listOfIds
     *
     * @throws Exception
     * @throws TypeError
     */
    private function doImport(array $listOfIds): void
    {
        for ($i = 0; $i < count($listOfIds) && $this->isImportingSuccessful === true; $i++) {
            try {
                $client = $this->getGoogleClient();
                $this->gdriveUserSession->importFile($listOfIds[$i], $client);
            } catch (Exception $e) {
                $this->isImportingSuccessful = false;
                $this->error = [
                    'code' => $e->getCode(),
                    'class' => get_class($e),
                    'msg' => $this->getExceptionMessage($e)
                ];
                break;
            }
        }
    }

    /**
     * @param Exception $e
     *
     * @return string
     */
    private function getExceptionMessage(Exception $e): string
    {
        $rawMessage = $e->getMessage();

        // parse Google APIs errors
        if ($e instanceof Google_Service_Exception and $jsonDecodedMessage = json_decode($rawMessage, true)) {
            if (isset($jsonDecodedMessage['error']['message'])) {
                return $jsonDecodedMessage['error']['message'];
            }

            if (isset($jsonDecodedMessage['error']['errors'])) {
                $arrayMsg = [];

                foreach ($jsonDecodedMessage['error']['errors'] as $error) {
                    $arrayMsg[] = $error['message'];
                }

                return implode(',', $arrayMsg);
            }

            return $jsonDecodedMessage;
        }

        return $rawMessage;
    }

    /**
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     */
    private function finalize(): void
    {
        if ($this->isAsyncReq) {
            $this->doResponse();
        } else {
            $this->doRedirect();
        }
    }

    /**
     * Cookie writer seam: overridable in tests to capture the emitted redirect cookies.
     */
    protected function cookieManager(): CookieManager
    {
        return new CookieManager();
    }

    /**
     * Google client factory seam: overridable in tests so the import loop can run
     * without live OAuth configuration (GoogleProvider::getClient throws when unset).
     *
     * @throws Exception
     * @throws \RuntimeException
     */
    protected function getGoogleClient(): \Google_Client
    {
        return (new GoogleProvider())->getClient(AppConfig::$HTTPHOST . "/gdrive/oauth/response");
    }

    private function doRedirect(): never
    {
        // set a cookie for callback outcome to allow the frontend to show errors
        $outcome = [
            "success" => $this->isImportingSuccessful,
            "error_msg" => isset($this->error['msg']) ? $this->formatErrorMessage($this->error['msg']) : null,
            "error_class" => $this->error['class'] ?? null,
            "error_code" => $this->error['code'] ?? null,
        ];

        $cookieManager = $this->cookieManager();
        $cookieManager->set(
            self::GDRIVE_OUTCOME_COOKIE_NAME,
            json_encode($outcome) ?: '',
            time() + 86400,
            true,
            false,
            'Strict'
        );

        // set a cookie to allow the frontend to call list endpoint
        $cookieManager->set(
            self::GDRIVE_LIST_COOKIE_NAME,
            $_SESSION[Constants::COOKIE_UPLOAD_TOKEN],
            time() + 86400
        );

        if (AppConfig::$ENV === 'testing') {
            throw new RenderTerminatedException();
        }

        die();
    }

    /**
     * @throws LockedResponseException
     * @throws ResponseAlreadySentException
     */
    private function doResponse(): void
    {
        $this->response->json([
            "success" => $this->isImportingSuccessful,
            "error_msg" => isset($this->error['msg']) ? $this->formatErrorMessage($this->error['msg']) : null,
            "error_class" => $this->error['class'] ?? null,
            "error_code" => $this->error['code'] ?? null,
        ]);
    }

    /**
     * @param $message
     *
     * @return string
     */
    private function formatErrorMessage(string $message): string
    {
        if ($message == "This file is too large to be exported.") {
            return "you are trying to upload a file bigger than 10 mb. Google Drive does not allow exports of files bigger than 10 mb. Please download the file and upload it from your computer.";
        }

        if ($message == "Export only supports Docs Editors files.") {
            return "Google Drive does not allow exports of files in this format. Please open the file in Google Drive and save it as a Google Drive file.";
        }

        if (str_contains($message, 'The specified key does not exist.')) {
            return "The name of the file you are trying to upload is too long, please shorten it and try again.";
        }

        return $message;
    }

    /**
     * @throws Exception
     */
    public function listImportedFiles(): void
    {
        try {
            $response = $this->gdriveUserSession->getFileStructureForJsonOutput();
            $this->response->json($response);

            // delete the cookie
            (new CookieManager())->delete(self::GDRIVE_LIST_COOKIE_NAME);
        } catch (S3Exception $e) {
            $errorCode = 400;
            $this->response->code($errorCode);
            $this->response->json([
                'code' => $errorCode,
                'class' => get_class($e),
                'msg' => $this->formatErrorMessage($this->getExceptionMessage($e))
            ]);
        } catch (Exception $e) {
            $errorCode = (int)$e->getCode() >= 400 ? $e->getCode() : 500;
            $this->response->code((int)$errorCode);
            $this->response->json([
                'code' => $errorCode,
                'class' => get_class($e),
                'msg' => $this->formatErrorMessage($this->getExceptionMessage($e))
            ]);
        }
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function changeConversionParameters(): void
    {
        $originalSourceLang = $_SESSION[Constants::SESSION_ACTUAL_SOURCE_LANG];
        $newSourceLang = filter_var($this->request->param('source'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $newSegmentationRule = filter_var($this->request->param('segmentation_rule'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $newFiltersExtractionTemplate = filter_var($this->request->param('filters_extraction_parameters_template'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);
        $newFiltersExtractionTemplateId = filter_var($this->request->param('filters_extraction_parameters_template_id'), FILTER_VALIDATE_INT);

        $filtersExtractionParameters = null;

        try {
            $languageHandler = Languages::getInstance();
            $newSourceLang = $languageHandler->validateLanguage($newSourceLang ?: null);

            if (!empty($newFiltersExtractionTemplate)) {
                $filtersExtractionParameters = new FiltersConfigTemplateStruct();
                $filtersExtractionParameters->hydrateFromJSON(html_entity_decode($newFiltersExtractionTemplate));
            } elseif (!empty($newFiltersExtractionTemplateId)) {
                $uid = $this->getUser()->uid ?? throw new TypeError('User uid is required');
                $filtersExtractionParameters = (new FiltersConfigTemplateDao($this->getDatabase()))->getByIdAndUser($newFiltersExtractionTemplateId, $uid);

                if ($filtersExtractionParameters === null) {
                    throw new Exception("filters_extraction_parameters_template_id not valid");
                }
            }

            $newSegmentationRule = Constants::validateSegmentationRules($newSegmentationRule ?: null);
        } catch (Exception $e) {
            $this->isImportingSuccessful = false;
            $this->error = [
                'code' => $e->getCode(),
                'class' => get_class($e),
                'msg' => $this->getExceptionMessage($e)
            ];

            return;
        }

        $success = $this->gdriveUserSession->reConvert($newSourceLang, $newSegmentationRule, $filtersExtractionParameters);

        if ($success) {
            $_SESSION[Constants::SESSION_ACTUAL_SOURCE_LANG] = $newSourceLang;
            $this->source_lang = $newSourceLang;
        } else {
            $_SESSION[Constants::SESSION_ACTUAL_SOURCE_LANG] = $originalSourceLang;
            $this->source_lang = $originalSourceLang;
        }

        $this->response->json([
            "success" => $success
        ]);
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function deleteImportedFile(): void
    {
        $fileId = $this->request->param('fileId');
        $segmentationRule = $this->request->param('segmentation_rule');
        $source = $this->request->param('source');
        $filtersTemplate = $this->request->param('filters_template');

        if ($fileId === 'all') {
            $this->gdriveUserSession->removeAllFiles($this->getDatabase(), $source, $segmentationRule, $filtersTemplate);
            $success = true;
        } else {
            $success = $this->gdriveUserSession->removeFile($this->getDatabase(), $fileId, $source, $segmentationRule, $filtersTemplate);

            if ($success) {
                if (!$this->gdriveUserSession->hasFiles()) {
                    $this->gdriveUserSession->clearSession();
                }
            }
        }

        $this->response->json([
            "success" => $success
        ]);
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    protected function initDependencies(): void
    {
        $this->initSessionService();
    }

}