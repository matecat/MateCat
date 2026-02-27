<?php

namespace Model\Conversion;

use DomainException;
use Exception;
use Model\FeaturesBase\FeatureSet;
use Model\FilesStorage\AbstractFilesStorage;
use Model\Filters\FiltersConfigTemplateStruct;
use ReflectionException;
use RuntimeException;
use Utils\Constants\ConversionHandlerStatus;

/**
 * Class FilesConverter
 *
 * Handles the conversion of files from a source language to a target language. The class supports
 * various types of files, including handling ZIP files and extracting their contents for conversion.
 * It manages the entire conversion process, including error handling, file warnings, and the accumulation
 * of results.
 *
 * **No input validation is performed by this class.**
 * Validation of the following inputs is intentionally outside the responsibility of this class
 * and must be performed by the caller before instantiation:
 *
 *  - **File names**: must be validated (e.g. via Utils::isValidFileName()) before being passed in.
 *  - **Upload token**: must be a valid, non-empty token (e.g. via Utils::isTokenValid()).
 *  - **Source and target languages**: must be valid language codes (e.g. via Languages::validateLanguage()).
 *  - **Segmentation rule**: must be a recognised rule or null (e.g. via Constants::validateSegmentationRules()).
 *
 * This class assumes all inputs are already sanitised and trusted.
 */
class FilesConverter
{
    private string $source_lang;
    private string $target_lang;
    private string $fullUploadDirPath;
    private string $errDir;
    private string $uploadTokenValue;
    private ?string $segmentation_rule;
    private array $files;

    /**
     * @var ConvertedFileList
     */
    private ConvertedFileList $resultStack;

    /**
     * @var FeatureSet
     */
    private FeatureSet $featureSet;

    /**
     * @var FiltersConfigTemplateStruct|null
     */
    private ?FiltersConfigTemplateStruct $filters_extraction_parameters;

    /**
     * @var bool|null
     */
    private ?bool $legacy_icu;
    private bool $icu_enabled;

    /**
     * FilesConverter constructor.
     *
     * @param array $files
     * @param string $source_lang
     * @param string $target_lang
     * @param string $intDir
     * @param string $errDir
     * @param string $uploadTokenValue
     * @param bool $icu_enabled
     * @param string|null $segmentation_rule
     * @param FeatureSet $featureSet
     * @param FiltersConfigTemplateStruct|null $filters_extraction_parameters
     * @param bool|null $legacy_icu
     */
    public function __construct(
        array $files,
        string $source_lang,
        string $target_lang,
        string $intDir,
        string $errDir,
        string $uploadTokenValue,
        bool $icu_enabled,
        ?string $segmentation_rule,
        FeatureSet $featureSet,
        ?FiltersConfigTemplateStruct $filters_extraction_parameters = null,
        ?bool $legacy_icu = false
    ) {
        $this->files = $files;
        $this->setSourceLang($source_lang);
        $this->setTargetLangs($target_lang);
        $this->fullUploadDirPath = $intDir;
        $this->errDir = $errDir;
        $this->uploadTokenValue = $uploadTokenValue;
        $this->segmentation_rule = $segmentation_rule;
        $this->featureSet = $featureSet;
        $this->filters_extraction_parameters = $filters_extraction_parameters;
        $this->legacy_icu = $legacy_icu;
        $this->icu_enabled = $icu_enabled;
        $this->resultStack = new ConvertedFileList();
    }

    /**
     * Sets the source language for the conversion.
     * No validation is performed — the caller is responsible for passing a valid language code.
     *
     * @param string $source_lang The source language code (e.g. "en-US").
     */
    private function setSourceLang(string $source_lang): void
    {
        $this->source_lang = $source_lang;
    }

    /**
     * Sets the target language(s) for the conversion.
     * Multiple languages can be passed as a comma-separated string (e.g. "it-IT,fr-FR").
     * No validation is performed — the caller is responsible for passing valid language codes.
     *
     * @param string $target_lang A comma-separated list of target language codes.
     */
    private function setTargetLangs(string $target_lang): void
    {
        $this->target_lang = $target_lang;
    }

    /**
     * @return ConvertedFileList
     * @throws Exception
     */
    public function convertFiles(): ConvertedFileList
    {
        foreach ($this->files as $fileName) {
            $ext = AbstractFilesStorage::pathinfo_fix($fileName, PATHINFO_EXTENSION);
            if ($ext == "zip") {
                $fileListContent = $this->getExtractedFilesContentList($fileName);

                foreach ($fileListContent as $internalFile) {
                    $ext = AbstractFilesStorage::pathinfo_fix($internalFile, PATHINFO_EXTENSION);
                    if ($ext == "zip") {
                        throw new DomainException("Nested zip files are not allowed.", ConversionHandlerStatus::NESTED_ZIP_FILES_NOT_ALLOWED);
                    }

                    $res = $this->convertFile($internalFile);
                    $this->resultStack->add($res);

                    if ($res->isError()) {
                        $this->resultStack->setErroredFile($res);
                    } elseif ($res->isWarning()) {
                        $this->resultStack->setWarnedFile($res);
                    }
                }
            } else {
                $res = $this->convertFile($fileName);
                $this->resultStack->add($res);

                if ($res->isError()) {
                    $this->resultStack->setErroredFile($res);
                } elseif ($res->isWarning()) {
                    $this->resultStack->setWarnedFile($res);
                }
            }
        }

        return $this->resultStack;
    }

    /**
     * @param string $fileName
     *
     * @return ConversionHandler
     * @throws Exception
     */
    private function getConversionHandlerInstance(string $fileName): ConversionHandler
    {
        $conversionHandler = new ConversionHandler();
        $conversionHandler->setFileName($fileName);
        $conversionHandler->setSourceLang($this->source_lang);
        $conversionHandler->setTargetLang($this->target_lang);
        $conversionHandler->setSegmentationRule($this->segmentation_rule);
        $conversionHandler->setUploadTokenValue($this->uploadTokenValue);
        $conversionHandler->setUploadDir($this->fullUploadDirPath);
        $conversionHandler->setErrDir($this->errDir);
        $conversionHandler->setFeatures($this->featureSet);
        $conversionHandler->setFiltersExtractionParameters($this->filters_extraction_parameters);
        $conversionHandler->setFiltersLegacyIcu($this->legacy_icu);
        $conversionHandler->setIcuEnabled($this->icu_enabled);

        return $conversionHandler;
    }

    /**
     * @param string $fileName
     *
     * @return ConvertedFileModel|null
     * @throws ReflectionException
     * @throws Exception
     */
    private function convertFile(string $fileName): ?ConvertedFileModel
    {
        $conversionHandler = $this->getConversionHandlerInstance($fileName);
        $conversionHandler->processConversion();

        return $conversionHandler->getResult();
    }

    /**
     * @throws Exception
     */
    private function getExtractedFilesContentList(string $zipName): array
    {
        $conversionHandler = $this->getConversionHandlerInstance($zipName);

        // this makes the conversion-handler accumulate eventual errors on files and continue
        $conversionHandler->setStopOnFileException(false);

        $internalZipFileNames = $conversionHandler->extractZipFile();
        //call convertFileWrapper and start conversions for each file

        if ($conversionHandler->zipExtractionErrorFlag) {
            $fileErrors = $conversionHandler->getZipExtractionErrorFiles();

            foreach ($fileErrors as $fileError) {
                if (count((array)$fileError->error) == 0) {
                    continue;
                }

                throw new RuntimeException($fileError->error['message'], $fileError->error['code']);
            }
        }

        if (empty($internalZipFileNames)) {
            $errors = $conversionHandler->getResult();
            throw new DomainException($errors->getMessage(), $errors->getCode());
        }

        return $internalZipFileNames;
    }

    /**
     * Returns the accumulated conversion result list after processing.
     *
     * @return ConvertedFileList
     */
    public function getResult(): ConvertedFileList
    {
        return $this->resultStack;
    }

}