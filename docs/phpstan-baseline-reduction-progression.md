# PHPStan Baseline Reduction — Comprehensive Progression

**Branch:** `context-review` (based on `develop`)  
**Date:** 2026-05-29 (last updated)  
**Commits (refactor + fix + security + test):** 364+

| Metric | develop (baseline) | context-review (current) | Delta |
|--------|-------------------|--------------------------|-------|
| **PHPStan baseline entries** | 7,366 | 1,972 | −5,394 (−73.2%) |
| **PHPStan — full codebase** | ~25,000 errors | **0 errors** | — |
| **PHPUnit tests** | ~2,248 | 6,251 | +4,003 (+178.1%) |
| **PHPUnit assertions** | ~19,449 | 16,870 | — |
| **Coverage — Classes** | 8.48% (53/625) | 33.43% (231/691) | +24.95% (+178 classes) |
| **Coverage — Methods** | 21.74% (844/3,883) | 62.65% (2,617/4,177) | +40.91% (+1,773 methods) |
| **Coverage — Lines** | 21.19% (7,273/34,320) | 63.13% (22,390/35,466) | +41.94% (+15,117 lines) |
| **New test files** | 235 | 420 | +185 |
| **Files fully clean (0 PHPStan errors)** | 0 | 320 | +320 |

---

## Strategy: Foundation-First, Cascade-Down

Fix **shared infrastructure classes first** — interfaces, abstract classes, base controllers — because every error fixed there often reveals or resolves errors in child classes automatically.

Execution order:
1. Engine hierarchy (AbstractEngine → concrete engines → results/factory) — widest inheritance tree
2. Controller abstracts (KleinController → AbstractDownloadController → auth layer)
3. DataAccess layer (DaoCacheTrait → AbstractDao → concrete DAOs)
4. Utility layer (CatUtils, Utils — called from everywhere)
5. Worker cluster (TMAnalysisWorker, GetContributionWorker, FastAnalysis)
6. High-value controllers (highest error count files)
7. Models & modules (TeamModel, FilesStorage, TmKeyManagement, Translators)

---

## Rules

### Core Process Rules

1. **TDD** — write good test coverage alongside every PHPStan improvement. Tests FIRST or alongside, never deferred.
2. **Verify ALL callers** — when changing/updating method signatures (parameters, return types), MUST verify ALL callers, child classes, and sibling implementations before committing.
3. **Types MUST be certain** — no speculative type changes. Confirm actual runtime behavior via tests/callers labefore narrowing or changing a type.
4. **Minimize scope** — fix the PHPStan error, don't refactor surrounding code.
5. **No `@phpstan-ignore`** or baseline suppression.

### Baseline Reduction Algorithm (MANDATORY)

Every file we touch **MUST** be clean. The baseline is managed by surgical removal, never regeneration.

1. **Maintain a fixed-files ledger** — a persistent list of every file we've already cleaned (see below).
2. **Pick a new file** to clean from the baseline.
3. **Fix all PHPStan errors** in that file.
4. **Test the file alone with no baseline** (`php vendor/bin/phpstan analyse <file> --configuration=phpstan-no-baseline.neon --no-progress --error-format=table`) — it **must** report zero errors.
5. **Run PHPStan on the full codebase with the baseline** — this surfaces only **new** errors (ones not already recorded in the baseline).
6. **For each new error found:**
   - If the error is in a file **on our fixed-files ledger** → **fix it** (that file must stay clean).
   - If the error is in a file **not on our ledger** → **add it to the baseline** (we haven't committed to cleaning it yet).
7. **Add the newly cleaned file** to the fixed-files ledger.
8. **Manually remove** all resolved entries for that file from `phpstan-baseline.neon`. **NEVER regenerate the baseline.** Regenerating resets the baseline to the current state, potentially re-whitelisting errors in files we've already committed to keeping clean.
9. **If you modified files other than the target** → each modified file must be checked with this algorithm. Repeat from step 2 for each one.
10. **Repeat from step 2** for the next target.

### TDD Specifics

- **Behavioral changes** (null guards, new exceptions, restructured control flow) → strict TDD red/green. Write the failing test FIRST (red), then apply the minimal fix (green).
- **Type-only annotations** (`@throws`, `@return`, `@param` PHPDocs) → don't require red/green since PHPStan itself is the verifier.

### Coverage Target

- Every file in the ledger must have **at least 80% test coverage**. When fixing PHPStan errors in a file, the goal is also to **increase test coverage above 80%** for that file. Tests must cover the fixed code paths, not just satisfy PHPStan.
- MANDATORY – Before starting the coverage increase, analyze blockers, show me the report about the blockers and the current coverage status.

### Commit / Git Rules

- **Conventional-commit with emoji prefix** — format: `<emoji> <type>(<scope>): <description>`
- **Full test suite must pass before commit**
- **Do NOT push without explicit user authorization** — commit and push are two separate gates
- **Always `-a` flag (lowercase)** for `git commit`
- Show commit message → WAIT for authorization → commit

### Progress Docs

- **Never modify baseline/starting values** in progress docs
- Only update current values, delta columns, completed rows, queue movements

---

## Fixed-Files Ledger

Every file listed here **MUST** have zero PHPStan errors when tested without a baseline. If a cascade fix introduces errors in any of these files, those errors must be fixed immediately — never added to the baseline.

**Total: 510 files** (verified via `git diff --name-only 7d529165b7...HEAD` cross-referenced with `phpstan-baseline.neon`)

<details>
<summary>Click to expand full ledger (436 files)</summary>

#### Controller Abstracts & Auth
| File | Cleaned In |
|------|-----------|
| `lib/Controller/Abstracts/AbstractDownloadController.php` | Phase 1B |
| `lib/Controller/Abstracts/Authentication/AuthCookie.php` | Phase 1E |
| `lib/Controller/Abstracts/Authentication/AuthenticationHelper.php` | Phase 1C |
| `lib/Controller/Abstracts/Authentication/AuthenticationTrait.php` | Phase 1G |
| `lib/Controller/Abstracts/Authentication/CookieManager.php` | Phase 1F |
| `lib/Controller/Abstracts/Authentication/SessionTokenStoreHandler.php` | Phase 1D |
| `lib/Controller/Abstracts/BaseKleinViewController.php` | Phase 31 |
| `lib/Controller/Abstracts/FlashMessage.php` | Phase 0 |
| `lib/Controller/Exceptions/RenderTerminatedException.php` | Phase 13D |
| `lib/Controller/API/Commons/Exceptions/ConflictError.php` | Phase N+ |
| `lib/Controller/Services/RateLimiterService.php` | Phase N+ |

#### Controller API
| File | Cleaned In |
|------|-----------|
| `lib/Controller/API/App/Authentication/LoginController.php` | Phase 37 |
| `lib/Controller/API/App/Authentication/SignupController.php` | Phase 37 |
| `lib/Controller/API/App/Authentication/LaraAuthController.php` | Phase 0 |
| `lib/Controller/API/App/Authentication/LaraAuthStandaloneController.php` | Phase N+ |
| `lib/Controller/API/App/Authentication/Traits/LaraAuthTrait.php` | Phase N+ |
| `lib/Controller/API/App/ConnectedServicesController.php` | Phase 40 |
| `lib/Controller/API/App/CommentController.php` | Phase 5D |
| `lib/Controller/API/App/CompletionEventController.php` | Phase 31 |
| `lib/Controller/API/App/ContextUrlController.php` | Phase 31 |
| `lib/Controller/API/App/DeleteContributionController.php` | Phase 5C |
| `lib/Controller/API/App/DownloadAnalysisReportController.php` | Phase 1B |
| `lib/Controller/API/App/EngineController.php` | Phase 0 |
| `lib/Controller/API/App/GetContributionController.php` | Phase 5C |
| `lib/Controller/API/App/GetSearchController.php` | Phase 5E |
| `lib/Controller/API/App/GetSegmentsController.php` | Phase 20 |
| `lib/Controller/API/App/GetWarningController.php` | Phase 22 |
| `lib/Controller/Views/CattoolController.php` | Phase 23 |
| `lib/Controller/Views/TemplateDecorator/AbstractDecorator.php` | Phase 23 |
| `lib/Controller/Views/TemplateDecorator/DownloadOmegaTOutputDecorator.php` | Phase 23 |
| `lib/Plugins/Features/ProjectCompletion/Decorator/CatDecorator.php` | Phase 23 |
| `lib/Utils/Templating/PHPTALWithAppend.php` | Phase 23 |
| `plugins/airbnb/lib/Features/Airbnb/Decorator/CatDecorator.php` | Phase 23 |
| `lib/Controller/API/App/QualityFrameworkController.php` | Phase 13C |
| `lib/Controller/API/App/QualityReportControllerAPI.php` | Phase 5C |
| `lib/Controller/API/App/SetTranslationController.php` | Phase 5 |
| `lib/Controller/API/V2/UserController.php` | Phase 37 |
| `lib/Controller/API/GDrive/GDriveController.php` | Phase 40 |
| `lib/Controller/API/V2/DownloadController.php` | Phase 14 |
| `lib/Controller/API/V2/JobsController.php` | Phase 31 |
| `lib/Controller/API/V2/ProjectCreationStatusController.php` | Phase 0 |
| `lib/Controller/API/V2/ReviseTranslationIssuesController.php` | Phase 31 |
| `lib/Controller/API/V2/SegmentTranslationIssueController.php` | Phase 5C |
| `lib/Controller/API/V2/SegmentVersionController.php` | Phase 31 | TO BE COVERED |
| `lib/Controller/API/V2/SplitJobController.php` | Phase 19 |
| `lib/Controller/API/V2/ChunkTranslationVersionController.php` | Phase N+ | TO BE COVERED |
| `lib/Controller/API/V2/ChunkTranslationIssueController.php` | Phase 5C |
| `lib/Controller/API/V2/KeyCheckController.php` | Phase 5C |
| `lib/Controller/API/V3/CancelRequestController.php` | Phase N+ |
| `lib/Controller/API/V3/LaraController.php` | Phase 0 |
| `lib/Controller/API/V3/ModernMTController.php` | Phase 21 |
| `lib/Controller/API/V3/ProjectTemplateController.php` | Phase 25+ |
| `lib/Controller/API/V3/QualityReportControllerAPI.php` | Phase 5C |
| `lib/Controller/API/V3/RevisionFeedbackController.php` | Phase 5C |
| `lib/Controller/API/V3/SegmentAnalysisController.php` | Phase 8A |
| `lib/Controller/API/V3/XliffConfigTemplateController.php` | Phase N+ |

#### Controller Traits & Views
| File | Cleaned In |
|------|-----------|
| `lib/Controller/Traits/APISourcePageGuesserTrait.php` | Phase 0 |
| `lib/Controller/Traits/ChunkNotFoundHandlerTrait.php` | Phase 5C |
| `lib/Controller/Traits/RateLimiterTrait.php` | Phase 5C |
| `lib/Controller/Traits/TimeLoggerTrait.php` | Phase 14 |
| `lib/Controller/Views/QualityReportController.php` | Phase 13C |

#### Model/DataAccess
| File | Cleaned In |
|------|-----------|
| `lib/Model/DataAccess/AbstractDao.php` | Phase 2A |
| `lib/Model/DataAccess/ArrayAccessTrait.php` | Phase 0 |
| `lib/Model/DataAccess/DaoCacheTrait.php` | Phase 2A |
| `lib/Model/DataAccess/Database.php` | Phase 0 |
| `lib/Model/DataAccess/IDatabase.php` | Phase 0 |
| `lib/Model/DataAccess/IDaoStruct.php` | Phase 32 |
| `lib/Model/DataAccess/UnknownPropertyException.php` | Phase 32 |
| `lib/Model/DataAccess/RecursiveArrayCopy.php` | Phase 0 |
| `lib/Model/DataAccess/ShapelessConcreteStruct.php` | Phase 2B |
| `lib/Model/DataAccess/TransactionalTrait.php` | Phase 7B |
| `lib/Model/DataAccess/XFetchEnvelope.php` | Phase 2D |

#### Model/ApiKeys & Comments
| File | Cleaned In |
|------|-----------|
| `lib/Model/ApiKeys/ApiKeyDao.php` | Phase 16 |
| `lib/Model/ApiKeys/ApiKeyStruct.php` | Phase N+ |
| `lib/Model/Comments/CommentDao.php` | Phase 16 |

#### Model/Conversion & Filters/DTO
| File | Cleaned In |
|------|-----------|
| `lib/Model/Conversion/ConversionHandler.php` | Phase 31 |
| `lib/Model/Conversion/ConvertedFileList.php` | Phase 31 |
| `lib/Model/Conversion/ConvertedFileModel.php` | Phase 31 |
| `lib/Model/Conversion/FilesConverter.php` | Phase 31 |
| `lib/Model/Conversion/Filters.php` | Phase 18 |
| `lib/Model/Conversion/InternalHashPaths.php` | Phase 31 |
| `lib/Model/Conversion/MimeTypes/Guesser/FileBinaryMimeTypeGuesser.php` | Phase 31 |
| `lib/Model/Conversion/MimeTypes/Guesser/FileinfoMimeTypeGuesser.php` | Phase 31 |
| `lib/Model/Conversion/MimeTypes/Guesser/SimpleMarkupMimeTypeGuesser.php` | Phase 31 |
| `lib/Model/Conversion/MimeTypes/MimeTypes.php` | Phase 31 |
| `lib/Model/Conversion/OCRCheck.php` | Phase 31 |
| `lib/Model/Conversion/Upload.php` | Phase 31 |
| `lib/Model/Conversion/UploadElement.php` | Phase 31 |
| `lib/Model/Conversion/ZipArchiveHandler.php` | Phase 31 |
| `lib/Model/Filters/DTO/IDto.php` | Phase 18 |
| `lib/Model/Filters/DTO/Dita.php` | Phase 18 |
| `lib/Model/Filters/DTO/Json.php` | Phase 18 |
| `lib/Model/Filters/DTO/MSExcel.php` | Phase 18 |
| `lib/Model/Filters/DTO/MSPowerpoint.php` | Phase 18 |
| `lib/Model/Filters/DTO/MSWord.php` | Phase 18 |
| `lib/Model/Filters/DTO/Xml.php` | Phase 18 |
| `lib/Model/Filters/DTO/Yaml.php` | Phase 18 |
| `lib/Model/Filters/FiltersConfigTemplateDao.php` | Phase 25 |
| `lib/Model/Filters/FiltersConfigTemplateStruct.php` | Phase N+ |

#### Model/Engines (Structs)
| File | Cleaned In |
|------|-----------|
| `lib/Model/Engines/EngineDAO.php` | Phase 0 |
| `lib/Model/Engines/Structs/AltlangStruct.php` | Phase 0 |
| `lib/Model/Engines/Structs/ApertiumStruct.php` | Phase 0 |
| `lib/Model/Engines/Structs/DeepLStruct.php` | Phase 0 |
| `lib/Model/Engines/Structs/EngineStruct.php` | Phase 0 |
| `lib/Model/Engines/Structs/GoogleTranslateStruct.php` | Phase 0 |
| `lib/Model/Engines/Structs/IntentoStruct.php` | Phase 0 |
| `lib/Model/Engines/Structs/LaraStruct.php` | Phase 0 |
| `lib/Model/Engines/Structs/MMTStruct.php` | Phase 0 |
| `lib/Model/Engines/Structs/NONEStruct.php` | Phase 0 |
| `lib/Model/Engines/Structs/SmartMATEStruct.php` | Phase 0 |
| `lib/Model/Engines/Structs/YandexTranslateStruct.php` | Phase 0 |

#### Model/FeaturesBase (Events)
| File | Cleaned In |
|------|-----------|
| `lib/Model/FeaturesBase/FeatureSet.php` | Phase 24 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/AnalysisBeforeMTGetContributionEvent.php` | Phase 5B |
| `lib/Model/FeaturesBase/Hook/Event/Filter/CharacterLengthCountEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/CheckTagMismatchEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/CheckTagPositionsEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/DecodeInstructionsEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/EncodeInstructionsEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/FilterActivityLogEntryEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/FilterContributionStructOnMTSetEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/FilterContributionStructOnSetTranslationEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/FilterJobPasswordToReviewPasswordEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/FilterRevisionChangeNotificationListEvent.php` | Phase 0 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/HandleJsonNotesBeforeInsertEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/IsAnInternalUserEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/OutsourceAvailableInfoEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/PopulatePreTranslationsEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/ProjectUrlsEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/RewriteContributionContextsEvent.php` | Phase 0 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/WordCountEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Run/AlterChunkReviewStructEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Run/ChunkReviewUpdatedEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Run/DecorateViewEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Run/FilterProjectNameModifiedEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Run/JobPasswordChangedEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Run/PostJobMergedEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Run/PostJobSplittedEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Run/PostProjectCreateEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Run/ProjectCompletionEventSavedEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Run/ReviewPasswordChangedEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Run/TmAnalysisDisabledEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Run/ValidateJobCreationEvent.php` | Phase 31 |
| `lib/Model/FeaturesBase/Hook/Event/Run/ValidateProjectCreationEvent.php` | Phase 31 |

#### Model/Files & FilesStorage
| File | Cleaned In |
|------|-----------|
| `lib/Model/Files/FilesInfoUtility.php` | Phase 34 |
| `lib/Model/Files/FilesPartsDao.php` | Phase 16 |
| `lib/Model/Files/FilesPartsStruct.php` | Phase 0 |
| `lib/Model/FilesStorage/AbstractFilesStorage.php` | Phase 6B |
| `lib/Model/FilesStorage/FsFilesStorage.php` | Phase 6B |
| `lib/Model/FilesStorage/IFilesStorage.php` | Phase 6B |

#### Model/Jobs & LQA
| File | Cleaned In |
|------|-----------|
| `lib/Model/Concerns/LogsMessages.php` | Phase 5C |
| `lib/Model/Jobs/JobDao.php` | Phase 5C |
| `lib/Model/Jobs/MetadataDao.php` | Phase 5C |
| `lib/Model/Jobs/JobStruct.php` | Phase 30 |
| `lib/Model/Jobs/JobsMetadataMarshaller.php` | Phase 30 |
| `lib/Model/Jobs/LexiQaAndTagProjectionLanguages.php` | Phase 30 |
| `lib/Model/Jobs/MetadataStruct.php` | Phase 30 |
| `lib/Model/Jobs/WarningsCountStruct.php` | Phase 30 |
| `lib/Model/JobSplitMerge/JobSplitMergeManager.php` | Phase 5C |
| `lib/Model/JobSplitMerge/JobSplitMergeService.php` | Phase 31 |
| `lib/Model/LQA/CategoryDao.php` | Phase 0 |
| `lib/Model/LQA/CategoryStruct.php` | Phase 47 |
| `lib/Model/LQA/ChunkReviewDao.php` | Phase 5C |
| `lib/Model/LQA/ChunkReviewStruct.php` | Phase 47 |
| `lib/Model/LQA/EntryCommentDao.php` | Phase 16 |
| `lib/Model/LQA/EntryCommentStruct.php` | Phase 47 |
| `lib/Model/LQA/EntryDao.php` | Phase 25 |
| `lib/Model/LQA/EntryStruct.php` | Phase N+ |
| `lib/Model/LQA/EntryValidator.php` | Phase 39 |
| `lib/Model/LQA/EntryWithCategoryStruct.php` | Phase 47 |
| `lib/Model/LQA/ModelDao.php` | Phase 0 |
| `lib/Model/LQA/ModelStruct.php` | Phase 0 |
| `lib/Model/LQA/QAModelInterface.php` | Phase 47 |
| `lib/Model/LQA/QAModelTemplate/QAModelTemplateCategoryStruct.php` | Phase 47 |
| `lib/Model/LQA/QAModelTemplate/QAModelTemplateDao.php` | Phase 25 |
| `lib/Model/LQA/QAModelTemplate/QAModelTemplatePassfailStruct.php` | Phase 47 |
| `lib/Model/LQA/QAModelTemplate/QAModelTemplatePassfailThresholdStruct.php` | Phase 47 |
| `lib/Model/LQA/QAModelTemplate/QAModelTemplateSeverityStruct.php` | Phase 47 |
| `lib/Model/LQA/QAModelTemplate/QAModelTemplateStruct.php` | Phase 47 |
| `lib/Model/Pagination/Pager.php` | Phase N+ |
| `lib/Model/Pagination/PaginationParameters.php` | Phase N+ |

#### Model/OwnerFeatures
| File | Cleaned In |
|------|-----------|
| `lib/Model/OwnerFeatures/OwnerFeatureDao.php` | Phase 15 |

#### Model/Projects
| File | Cleaned In |
|------|-----------|
| `lib/Model/Projects/ManageModel.php` | Phase 14 |
| `lib/Model/Projects/MetadataDao.php` | Phase 15 |
| `lib/Model/Projects/ProjectDao.php` | Phase 15 |
| `lib/Model/Projects/ProjectModel.php` | Phase 5C |
| `lib/Model/Projects/ProjectsMetadataMarshaller.php` | Phase 31 |
| `lib/Model/Projects/ProjectStruct.php` | Phase 14 |
| `lib/Model/Projects/ProjectTemplateDao.php` | Phase 5C |
| `lib/Model/Projects/ProjectTemplateStruct.php` | Phase 0 |

#### Model (other)
| File | Cleaned In |
|------|-----------|
| `lib/Model/ActivityLog/ActivityLogDao.php` | Phase 25 |
| `lib/Model/Analysis/AbstractStatus.php` | Phase 31 |
| `lib/Model/Analysis/AnalysisDao.php` | Phase 25 |
| `lib/Model/Analysis/Constants/AbstractConstants.php` | Phase 31 |
| `lib/Model/Analysis/Constants/ConstantsInterface.php` | Phase 31 |
| `lib/Model/Analysis/PayableRates.php` | Phase 31 |
| `lib/Model/Analysis/XTRFStatus.php` | Phase 31 |
| `lib/Model/ChunksCompletion/ChunkCompletionEventDao.php` | Phase 25 |
| `lib/Model/ChunksCompletion/ChunkCompletionUpdateDao.php` | Phase 25 |
| `lib/Model/ConnectedServices/ConnectedServiceDao.php` | Phase 25 |
| `lib/Model/ConnectedServices/ConnectedServiceStruct.php` | Phase 0 |
| `lib/Model/ConnectedServices/Oauth/OauthTokenEncryption.php` | Phase 0 |
| `lib/Model/ConnectedServices/Oauth/DefuseEncryption.php` | Phase 40 |
| `lib/Model/ConnectedServices/Oauth/Facebook/FacebookProvider.php` | Phase 40 |
| `lib/Model/ConnectedServices/Oauth/Github/GithubProvider.php` | Phase 40 |
| `lib/Model/ConnectedServices/Oauth/Google/AccessToken.php` | Phase 40 |
| `lib/Model/ConnectedServices/Oauth/Google/GoogleClientLogsFormatter.php` | Phase 40 |
| `lib/Model/ConnectedServices/Oauth/Google/GoogleProvider.php` | Phase 40 |
| `lib/Model/ConnectedServices/Oauth/LinkedIn/LinkedInProvider.php` | Phase 40 |
| `lib/Model/ConnectedServices/Oauth/LinkedIn/LinkedinFinal.php` | Phase 40 |
| `lib/Model/ConnectedServices/Oauth/Microsoft/MicrosoftProvider.php` | Phase 40 |
| `lib/Model/ConnectedServices/Oauth/OauthClient.php` | Phase 40 |
| `lib/Model/ConnectedServices/Oauth/ProviderInterface.php` | Phase 40 |
| `lib/Model/ConnectedServices/GDrive/GDriveTokenHandler.php` | Phase 28 |
| `lib/Model/ConnectedServices/GDrive/GDriveTokenVerifyModel.php` | Phase 28 |
| `lib/Model/ConnectedServices/GDrive/GDriveUserAuthorizationModel.php` | Phase 28 |
| `lib/Model/ConnectedServices/GDrive/RemoteFileService.php` | Phase 28 |
| `lib/Model/ConnectedServices/GDrive/Session.php` | Phase 28 |
| `lib/Model/ProjectCreation/FileInsertionException.php` | Phase 46 |
| `lib/Model/ProjectCreation/FileInsertionService.php` | Phase 40 |
| `lib/Model/ProjectCreation/ProjectCreationError.php` | Phase 46 |
| `lib/Model/ProjectCreation/ProjectManager.php` | Phase 31 |
| `lib/Model/ProjectCreation/ProjectManagerModel.php` | Phase 46 |
| `lib/Model/ProjectCreation/ProjectMetadataService.php` | Phase 46 |
| `lib/Model/ProjectCreation/ProjectStructure.php` | Phase N+ |
| `lib/Model/ProjectCreation/QAProcessor.php` | Phase 46 |
| `lib/Model/ProjectCreation/SegmentExtractor.php` | Phase 46 |
| `lib/Model/ProjectCreation/SegmentStorageService.php` | Phase 46 |
| `lib/Model/ProjectCreation/TmKeyService.php` | Phase 46 |
| `lib/Model/ProjectCreation/TranslationTuple.php` | Phase 46 |
| `lib/Model/Files/FileDao.php` | Phase 25 |
| `lib/Model/Files/MetadataDao.php` | Phase 25 |
| `lib/Model/MTQE/PayableRate/MTQEPayableRateTemplateDao.php` | Phase 25 |
| `lib/Model/MTQE/Templates/MTQEWorkflowTemplateDao.php` | Phase 25 |
| `lib/Model/Outsource/ConfirmationDao.php` | Phase 25 |
| `lib/Model/Outsource/ConfirmationStruct.php` | Phase 0 |
| `lib/Model/PayableRates/CustomPayableRateDao.php` | Phase 25 |
| `lib/Model/Propagation/PropagationTotalStruct.php` | Phase 0 |
| `lib/Model/QualityReport/QualityReportDao.php` | Phase 13 |
| `lib/Model/QualityReport/QualityReportModel.php` | Phase 13B |
| `lib/Model/QualityReport/QualityReportSegmentModel.php` | Phase 13B |
| `lib/Model/QualityReport/QualityReportSegmentStruct.php` | Phase 13A |
| `lib/Model/RemoteFiles/RemoteFileDao.php` | Phase 25 |
| `lib/Model/ReviseFeedback/FeedbackDAO.php` | Phase 0 |
| `lib/Model/Search/SearchModel.php` | Phase 35 |
| `lib/Model/Search/MySQLReplaceEventDao.php` | Phase 0 |
| `lib/Model/Search/MySQLReplaceEventIndexDao.php` | Phase 0 |
| `lib/Model/Search/RedisReplaceEventDao.php` | Phase 25 |
| `lib/Model/Search/RedisReplaceEventIndexDao.php` | Phase 0 |
| `lib/Model/Search/ReplaceEventDAOInterface.php` | Phase 40 |
| `lib/Model/Search/ReplaceEventIndexDaoInterface.php` | Phase 40 |
| `lib/Model/Search/ReplaceEventStruct.php` | Phase 40 |
| `lib/Model/Search/ReplaceEventCurrentVersionStruct.php` | Phase 40 |
| `lib/Model/Search/SearchQueryParamsStruct.php` | Phase 40 |
| `lib/Utils/Search/ReplaceHistory.php` | Phase 40 |
| `lib/Utils/Search/ReplaceHistoryFactory.php` | Phase 40 |
| `lib/Model/Segments/ContextGroupDao.php` | Phase 25 |
| `lib/Model/Segments/ContextResType.php` | Phase 31 |
| `lib/Model/Segments/ContextUrlResolver.php` | Phase 31 |
| `lib/Model/Segments/SegmentDao.php` | Phase 25 |
| `lib/Model/Segments/SegmentDisabledService.php` | Phase 5C |
| `lib/Model/Segments/SegmentMetadataDao.php` | Phase 5C |
| `lib/Model/Segments/SegmentNoteDao.php` | Phase 25 |
| `lib/Model/Segments/SegmentOriginalDataDao.php` | Phase 0 |
| `lib/Model/Segments/SegmentUIStruct.php` | Phase 0 |
| `lib/Model/Teams/InvitedUser.php` | Phase 44 |
| `lib/Model/Teams/MembershipDao.php` | Phase 15 |
| `lib/Model/Teams/MembershipStruct.php` | Phase 0 |
| `lib/Model/Teams/PendingInvitations.php` | Phase 44 |
| `lib/Model/Teams/TeamDao.php` | Phase 5C |
| `lib/Model/Teams/TeamModel.php` | Phase 6A |
| `lib/Model/Teams/TeamStruct.php` | Phase 44 |
| `lib/Model/TmKeyManagement/MemoryKeyDao.php` | Phase 6C |
| `lib/Model/TmKeyManagement/MemoryKeyStruct.php` | Phase 6C |
| `lib/Model/TmKeyManagement/UserKeysModel.php` | Phase 6C |
| `lib/Model/TMSService/TMSServiceDao.php` | Phase 25 |
| `lib/Model/Translations/SegmentTranslationDao.php` | Phase 25 |
| `lib/Model/Translations/WarningDao.php` | Phase 25 |
| `lib/Model/TranslationsSplit/SplitDAO.php` | Phase 25 |
| `lib/Model/Translators/JobsTranslatorsDao.php` | Phase 25 |
| `lib/Model/Translators/JobsTranslatorsStruct.php` | Phase 0 |
| `lib/Model/Translators/TranslatorsModel.php` | Phase 6D |
| `lib/Model/Translators/TranslatorsProfilesDao.php` | Phase 25 |
| `lib/Model/Users/Authentication/ChangePasswordModel.php` | Phase 43 |
| `lib/Model/Users/Authentication/OAuthSignInModel.php` | Phase 37 |
| `lib/Model/Users/Authentication/PasswordResetModel.php` | Phase 43 |
| `lib/Model/Users/Authentication/SignupModel.php` | Phase 26 |
| `lib/Model/Users/ClientUserFacade.php` | Phase 43 |
| `lib/Model/Users/MetadataDao.php` | Phase 25 |
| `lib/Model/Users/MetadataStruct.php` | Phase 43 |
| `lib/Model/Users/UserDao.php` | Phase 5C |
| `lib/Model/Users/UserStruct.php` | Phase 43 |
| `lib/Model/FeaturesBase/BasicFeatureStruct.php` | Phase 27 |
| `lib/Model/FeaturesBase/FeatureCodes.php` | Phase 27 |
| `lib/Model/FeaturesBase/FeatureSet.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/AppendFieldToAnalysisObjectEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/AppendInitialTemplateVarsEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/CorrectTagErrorsEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/FilterCreateProjectFeaturesEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/FilterGetSegmentsResultEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/FilterMyMemoryGetParametersEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/FilterPayableRatesEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/InjectExcludedTagsInQaEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/PrepareNotesForRenderingEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/SanitizeOriginalDataMapEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/Event/Run/BeforeProjectCreationEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/Event/Run/PostAddSegmentTranslationEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/Event/Run/SetTranslationCommittedEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/FilterEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/Hook/RunEvent.php` | Phase 27 |
| `lib/Model/FeaturesBase/PluginsLoader.php` | Phase 28 |
| `lib/Plugins/Features/BaseFeature.php` | Phase 28 |
| `lib/Model/WordCount/CounterModel.php` | Phase 31 |
| `lib/Model/WordCount/WordCounterDao.php` | Phase 25 |
| `lib/Model/Xliff/DTO/AbstractXliffRule.php` | Phase 0 |
| `lib/Model/Xliff/DTO/DefaultRule.php` | Phase 42 |
| `lib/Model/Xliff/DTO/XliffRuleInterface.php` | Phase 0 |
| `lib/Model/Xliff/DTO/XliffRulesModel.php` | Phase 42 |
| `lib/Model/Xliff/XliffConfigTemplateDao.php` | Phase 25 |
| `lib/Model/Xliff/XliffConfigTemplateStruct.php` | Phase 42 |

#### Plugins
| File | Cleaned In |
|------|-----------|
| `lib/Plugins/Features/AbstractRevisionFeature.php` | Phase 33 |
| `lib/Plugins/Features/RevisionFactory.php` | Phase 13A |
| `lib/Plugins/Features/ReviewExtended/BatchReviewProcessor.php` | Phase 31 |
| `lib/Plugins/Features/ReviewExtended/ChunkReviewModel.php` | Phase 31 |
| `lib/Plugins/Features/ReviewExtended/Email/BatchReviewProcessorAlertEmail.php` | Phase 31 |
| `lib/Plugins/Features/ReviewExtended/Email/RevisionChangedNotificationEmail.php` | Phase 31 |
| `lib/Plugins/Features/ReviewExtended/IChunkReviewModel.php` | Phase 31 |
| `lib/Plugins/Features/ReviewExtended/ReviewUtils.php` | Phase 31 |
| `lib/Plugins/Features/ReviewExtended/TranslationIssueModel.php` | Phase 31 |
| `lib/Plugins/Features/SegmentFilter/Model/SegmentFilterDao.php` | Phase 0 |
| `lib/Plugins/Features/TranslationEvents/Model/TranslationEventDao.php` | Phase 12A |
| `lib/Plugins/Features/TranslationVersions/Model/TranslationVersionDao.php` | Phase 0 |

#### Routes
| File | Cleaned In |
|------|-----------|
| `lib/Routes/api_v3_routes.php` | Phase 31 |
| `lib/Routes/view_routes.php` | Phase 31 |

#### Utils/Workers & Contribution
| File | Cleaned In |
|------|-----------|
| `daemons/FastAnalysis.php` | Phase 4B |
| `lib/Utils/AsyncTasks/Workers/Analysis/FastAnalysis.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/RedisKeys.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/DTO/AnalysisResult.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Interface/AnalysisRedisServiceInterface.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Interface/EngineResolverInterface.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Interface/EngineServiceInterface.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Interface/MatchProcessorServiceInterface.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Interface/ProjectCompletionRepositoryInterface.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Interface/ProjectCompletionServiceInterface.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Interface/SegmentUpdaterServiceInterface.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/AnalysisRedisService.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/DefaultEngineResolver.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/EngineService.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/MatchProcessorService.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/ProjectCompletionRepository.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/ProjectCompletionService.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysis/Service/SegmentUpdaterService.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Analysis/TMAnalysisWorker.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/GetContributionWorker.php` | Phase 4A |
| `lib/Utils/AsyncTasks/Workers/Interface/MatchSorterInterface.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/Service/MatchSorter.php` | Phase 5C |
| `lib/Utils/AsyncTasks/Workers/SetContributionMTWorker.php` | Phase 5B |
| `lib/Utils/AsyncTasks/Workers/SetContributionWorker.php` | Phase 5B |
| `lib/Utils/AsyncTasks/Workers/GlossaryWorker.php` | Phase 17 |
| `lib/Utils/AsyncTasks/Workers/Traits/ProjectWordCount.php` | Phase 4C |
| `lib/Utils/Constants/Constants.php` | Phase 45 |
| `lib/Utils/Constants/EngineConstants.php` | Phase 6C |
| `lib/Utils/Constants/Ices.php` | Phase 45 |
| `lib/Utils/Constants/JobStatus.php` | Phase 45 |
| `lib/Utils/Constants/Mime2Extension.php` | Phase 45 |
| `lib/Utils/Constants/ProjectStatus.php` | Phase 45 |
| `lib/Utils/Constants/Teams.php` | Phase 45 |
| `lib/Utils/Constants/TmKeyPermissions.php` | Phase 45 |
| `lib/Utils/Constants/TranslationStatus.php` | Phase 45 |
| `lib/Utils/Constants/XliffTranslationStatus.php` | Phase 45 |
| `lib/Utils/Contribution/ContributionContexts.php` | Phase 4A |
| `lib/Utils/Contribution/GetContributionRequest.php` | Phase 4A |
| `lib/Utils/Contribution/SetContributionRequest.php` | Phase 5B |
| `lib/Utils/Date/DateTimeUtil.php` | Phase 0 |

#### Utils/Email
| File | Cleaned In |
|------|-----------|
| `lib/Utils/Email/BaseCommentEmail.php` | Phase 31 |
| `lib/Utils/Email/CommentEmail.php` | Phase 31 |
| `lib/Utils/Email/CommentMentionEmail.php` | Phase 31 |
| `lib/Utils/Email/CommentResolveEmail.php` | Phase 31 |
| `lib/Utils/Email/MembershipCreatedEmail.php` | Phase 12A |
| `lib/Utils/Email/MembershipDeletedEmail.php` | Phase 12A |

#### Utils/Engines (full hierarchy)
| File | Cleaned In |
|------|-----------|
| `lib/Utils/Engines/AbstractEngine.php` | Phase 0 |
| `lib/Utils/Engines/Altlang.php` | Phase 0 |
| `lib/Utils/Engines/Apertium.php` | Phase 0 |
| `lib/Utils/Engines/DeepL.php` | Phase 0 |
| `lib/Utils/Engines/EngineInterface.php` | Phase 0 |
| `lib/Utils/Engines/EnginesFactory.php` | Phase 0 |
| `lib/Utils/Engines/GoogleTranslate.php` | Phase 0 |
| `lib/Utils/Engines/Intento.php` | Phase 14 |
| `lib/Utils/Engines/Lara.php` | Phase 14 |
| `lib/Utils/Engines/Lara/HeaderField.php` | Phase 0 |
| `lib/Utils/Engines/Lara/Headers.php` | Phase 0 |
| `lib/Utils/Engines/Lara/HttpClientInterface.php` | Phase 0 |
| `lib/Utils/Engines/Lara/LaraClient.php` | Phase 0 |
| `lib/Utils/Engines/MMT/MMTServiceApi.php` | Phase 14 |
| `lib/Utils/Engines/MMT/MMTServiceApiException.php` | Phase 0 |
| `lib/Utils/Engines/MMT.php` | Phase 14 |
| `lib/Utils/Engines/MyMemory.php` | Phase 0 |
| `lib/Utils/Engines/NONE.php` | Phase 0 |
| `lib/Utils/Engines/SmartMATE.php` | Phase 5C |
| `lib/Utils/Engines/Results/ErrorResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MTResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/AnalyzeResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/AuthKeyResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/CheckGlossaryResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/CreateUserResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/DomainsResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/ExportResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/FileImportAndStatusResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/GetGlossaryResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/GetMemoryResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/KeysGlossaryResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/Matches.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/SearchGlossaryResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/SetContributionResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/TagProjectionResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/MyMemory/UpdateContributionResponse.php` | Phase 0 |
| `lib/Utils/Engines/Results/TMSAbstractResponse.php` | Phase 14 |
| `lib/Utils/Engines/Traits/HotSwap.php` | Phase 14 |
| `lib/Utils/Engines/Traits/Oauth.php` | Phase 0 |
| `lib/Utils/Engines/Validators/AltLangEngineValidator.php` | Phase 14 |
| `lib/Utils/Engines/Validators/Contracts/EngineValidatorObject.php` | Phase 0 |
| `lib/Utils/Engines/Validators/DeepLEngineValidator.php` | Phase 14 |
| `lib/Utils/Engines/Validators/GoogleTranslateEngineValidator.php` | Phase 0 |
| `lib/Utils/Engines/Validators/IntentoEngineOptionsValidator.php` | Phase 0 |
| `lib/Utils/Engines/Validators/IntentoEngineValidator.php` | Phase 0 |
| `lib/Utils/Engines/Validators/LaraEngineValidator.php` | Phase 0 |
| `lib/Utils/Engines/Validators/LaraGlossaryValidator.php` | Phase 0 |
| `lib/Utils/Engines/Validators/MMTEngineValidator.php` | Phase 0 |
| `lib/Utils/Engines/Validators/MMTGlossaryValidator.php` | Phase 0 |
| `lib/Utils/Engines/YandexTranslate.php` | Phase 0 |

#### Utils/LQA
| File | Cleaned In |
|------|-----------|
| `lib/Utils/LQA/BxExG/Element.php` | Phase 9A |
| `lib/Utils/LQA/BxExG/Mapper.php` | Phase 9A |
| `lib/Utils/LQA/BxExG/Validator.php` | Phase 9A |
| `lib/Utils/LQA/ICUSourceSegmentChecker.php` | Phase 14 |
| `lib/Utils/LQA/ICUSourceSegmentDetector.php` | Phase 14 |
| `lib/Utils/LQA/QA/ErrObject.php` | Phase 9A |
| `lib/Utils/LQA/QA/SizeRestrictionChecker.php` | Phase 31 |
| `lib/Utils/LQA/QA/SymbolChecker.php` | Phase 9A |
| `lib/Utils/LQA/SizeRestriction/CJKLangUtils.php` | Phase 9A |
| `lib/Utils/LQA/SizeRestriction/EmojiUtils.php` | Phase 9A |

#### Utils (other)
| File | Cleaned In |
|------|-----------|
| `lib/Utils/ActiveMQ/AMQHandler.php` | Phase 27 |
| `lib/Utils/Logger/MatecatLogger.php` | Phase 12A |
| `lib/Utils/Network/MultiCurlHandler.php` | Phase 27 |
| `lib/Utils/Redis/RedisHandler.php` | Phase N+ |
| `lib/Utils/TaskRunner/Commons/SignalHandlerTrait.php` | Phase 26 |
| `lib/Utils/TaskRunner/Commons/AbstractDaemon.php` | Phase 31 |
| `lib/Utils/TaskRunner/Commons/AbstractElement.php` | Phase 31 |
| `lib/Utils/TaskRunner/Commons/AbstractWorker.php` | Phase 31 |
| `lib/Utils/TaskRunner/Commons/Configuration.php` | Phase 31 |
| `lib/Utils/TaskRunner/Commons/Context.php` | Phase 31 |
| `lib/Utils/TaskRunner/Commons/ContextList.php` | Phase 31 |
| `lib/Utils/TaskRunner/Commons/NativeProcessControl.php` | Phase 31 |
| `lib/Utils/TaskRunner/Commons/Params.php` | Phase 31 |
| `lib/Utils/TaskRunner/Commons/ProcessControlInterface.php` | Phase 31 |
| `lib/Utils/TaskRunner/Commons/QueueElement.php` | Phase 31 |
| `lib/Utils/TaskRunner/Exceptions/EmptyElementException.php` | Phase 31 |
| `lib/Utils/TaskRunner/Exceptions/EndQueueException.php` | Phase 31 |
| `lib/Utils/TaskRunner/Exceptions/FrameException.php` | Phase 31 |
| `lib/Utils/TaskRunner/Exceptions/NotSupportedMTException.php` | Phase 31 |
| `lib/Utils/TaskRunner/Exceptions/ReQueueException.php` | Phase 31 |
| `lib/Utils/TaskRunner/Exceptions/WorkerClassException.php` | Phase 31 |
| `lib/Utils/TaskRunner/Executor.php` | Phase 26 |
| `lib/Utils/TaskRunner/executor_worker.php` | Phase 31 |
| `lib/Utils/TaskRunner/TaskManager.php` | Phase 31 |
| `lib/Utils/TmKeyManagement/Filter.php` | Phase 6C |
| `lib/Utils/TmKeyManagement/ShareKeyEmail.php` | Phase 6C |
| `lib/Utils/TmKeyManagement/TmKeyManager.php` | Phase 6C |
| `lib/Utils/TmKeyManagement/TmKeyStruct.php` | Phase 6C |
| `lib/Utils/Tools/CatUtils.php` | Phase 3A |
| `lib/Utils/Tools/PostEditing.php` | Phase 31 |
| `lib/Utils/Tools/SimpleJWT.php` | Phase 0 |
| `lib/Utils/Tools/Utils.php` | Phase 3B |
| `lib/Utils/Registry/AppConfig.php` | Phase 32 |
| `lib/Utils/Validator/IsJobRevisionValidator.php` | Phase 13A |

#### View
| File | Cleaned In |
|------|-----------|
| `lib/View/API/V3/Json/FilesInfo.php` | Phase 34 |
| `lib/View/API/App/Json/Analysis/AnalysisFile.php` | Phase 12A |
| `lib/View/API/App/Json/Analysis/AnalysisJobSummary.php` | Phase N+ |
| `lib/View/API/App/Json/Analysis/AnalysisFileMetadata.php` | Phase 12A |
| `lib/View/API/V2/Json/JobTranslator.php` | Phase 0 |
| `lib/View/API/V2/Json/Membership.php` | Phase 12A |
| `lib/View/API/V3/Json/QualitySummary.php` | Phase 16 |
| `lib/View/fileupload/index.php` | Phase 31 |
| `lib/View/fileupload/UploadHandler.php` | Phase 31 |

#### TODO COVERAGE
Files that are PHPStan-clean but not yet covered by the test suite (controllers/routes/views not instrumented during unit tests).

| File | Cleaned In | Notes |
|------|-----------|-------|
| `lib/Controller/Abstracts/BaseKleinViewController.php` | Phase 31 | Base controller class |
| `lib/Controller/API/App/CompletionEventController.php` | Phase 31 | API controller |
| `lib/Controller/API/App/ContextUrlController.php` | Phase 31 | API controller |
| `lib/Controller/API/V2/JobsController.php` | Phase 31 | API controller |
| `lib/Controller/API/V2/ReviseTranslationIssuesController.php` | Phase 31 | API controller |
| `lib/Controller/API/V2/SegmentVersionController.php` | Phase 31 | API controller — TO BE COVERED |
| `lib/Model/Segments/ContextResType.php` | Phase 31 | Enum — no executable lines |
| `lib/Routes/api_v3_routes.php` | Phase 31 | Route definitions |
| `lib/Routes/view_routes.php` | Phase 31 | Route definitions |
| `lib/View/fileupload/index.php` | Phase 31 | View template |
| `lib/Model/ProjectCreation/JobCreationService.php` | Phase 32 | DAO migration — CustomPayableRateDao DI |
| `lib/Controller/API/V1/NewController.php` | Phase 32 | PHPStan-clean, DAO migration caller |

</details>

---

## Completed Work

### Phase 0: Structs & Engine Hierarchy (~1,100 errors)

**Why:** The engine hierarchy is the widest inheritance tree in the codebase. AbstractEngine → 10+ concrete engines → Results classes → Factory. Fixing it first propagates type safety to all engine consumers.

| # | Scope | Errors Fixed | Commit |
|---|-------|--------------|--------|
| 1 | EngineStruct + 11 subclasses | 43 | `dab5d87bc8` |
| 2 | 9 struct `iterableValue` fixes | 31 | `6ec492f326` |
| 3 | SegmentUIStruct, MembershipStruct, ConfirmationStruct, PropagationTotalStruct | 31 | `295a73b1bf` |
| 4 | ChunkDao, ProjectDao | 7 | `7a3e36d0fb` |
| 5 | 12 DAO files | 32 | `9bd1630414` |
| 6 | 65 struct @throws annotations | — | `dafe761033` |
| 7 | ProjectTemplateStruct | 28 | `295c1c79f0` |
| 8 | AbstractXliffRule | 26 | `1610122f4b` |
| 9 | ConnectedServiceStruct + AbstractDaoObjectStruct | 30 | `287700a975` |
| 10 | AbstractEngine (38 in-file + 33 cascaded) | 71 | `2412061ebf` |
| 11 | MMT engine layer (type-safe API client) | 117 | `157b2a681d` |
| 12 | MyMemory engine + result structs | 66 | `8bfad25f72` |
| 13 | Lara engine, validators, controllers | 80 | `ed83e0d321` |
| 14 | 7 sibling engines (Intento, SmartMATE, DeepL, Apertium, Altlang, Google, Yandex) | 83 | `a1514b8438` |
| 15 | Results/ response classes | 40 | `907c10531b` |
| 16 | EngineInterface, SmartMATE, Oauth trait, EngineController | 37 | `73d3cda245` |
| 17 | EnginesFactory, NONE, EngineOwnershipValidator | 28 | `add058c639` |
| 18 | SimpleJWT (typed ArrayAccess, null guards) | 29 | `990b466cbe` |
| 19 | Database, BaseKleinViewController, foundation layers | 59 | `18ab7162ee` |

---

### Phase 1: Controller Abstracts Layer (~185 errors) — ✅ DONE

**Why:** Every HTTP controller in Matecat inherits from this chain. Fixing it unlocks clean analysis for all 980 errors in `lib/Controller/API/`.

#### 1A. `KleinController.php` — ✅ DONE (commit `67cf2372b4`)

All 15 baseline entries eliminated. Cascade bonus: ~35 `FeatureSet|null` entries across Controller/ files eliminated by making `$featureSet` non-nullable.

**Total: −50 errors (15 direct + ~35 cascade)**

#### 1B. `AbstractDownloadController.php` + all 4 subclasses — ✅ DONE (commit `e122f8e04d`)

**95 baseline entries eliminated.** Changes:
- Parent: `finalize(): void`, `nocache(): void`, `setMimeType(): void`, `unlockToken(?array): void`; null guards; `pathinfo_fix` type safety
- `DownloadController`: `pathinfoString()` helper; filter_var casts; null guards; `@throws`; dead code removal; typed `$downloadToken`
- `DownloadOriginalController`: `void` return; filter_var casts; null guards on ChunkReview + Project
- `DownloadJobTMXController`: `SplFileInfo` → `SplTempFileObject`; `is_string()` iteration guard; filter_var casts
- `DownloadAnalysisReportController`: **Bug fix** — `InvalidArgumentException` constructor args were swapped; null-coalesce on `findById()`
- `ActivityLogStruct::$ip` → `?string` (−10 cascade entries across 10 files)
- 35 new tests (25 parent + 10 subclasses)

Residual: 9 entries remain (8 in DownloadController, 1 in DownloadOriginal) — cross-file type issues, will resolve in later phases.

#### 1C. `AuthenticationHelper.php` — ✅ DONE (commit `866e3545eb`, −16 entries)

- `$logged` type annotation `@var true` → `bool`
- `$session` property + all 4 method params typed as `array<string, mixed>`
- Null guard on `$userDao->getByUid()` return (`?UserStruct` → non-nullable)
- Null guard on `$api_record` before `->getUser()` call
- `getUserProfile()`: `@return array<string, mixed>`, `@throws Exception`
- `findUserTeams() ?? []` — null-safe for `array_map`
- Removed unused `use ($membersDao)` closure capture
- Removed invalid `@var $user UserStruct` and `@var $team TeamStruct` inline tags
- `validKeys()`: `@throws PDOException`
- `setUserSession()`: `@throws Exception`
- Removed unused `TeamStruct` import
- Cascade: `TypeError` catch widened in constructor inner try/catch
- Cascade: `destroyAuthentication()` gains `@throws Exception|TypeError`
- **9 tests** in `AuthenticationHelperTest.php`

#### 1D. `SessionTokenStoreHandler.php` — ✅ DONE (commit `866e3545eb`, −11 stale + 2 real)

- `setCookieLoginTokenActive()`: `@throws Exception` (propagated from `_cacheSetConnection()`)
- `isLoginCookieStillActive()`: `@throws Exception`
- 9 other baseline entries were stale (DaoCacheTrait was already fixed upstream)
- **7 tests** in `SessionTokenStoreHandlerTest.php`

#### 1E. `AuthCookie.php` — ✅ DONE (commit `866e3545eb`, −11 real errors)

- `getCredentials()`: `@return ?array<string, mixed>`, `@throws Exception|TypeError`
- `setCredentials()`: `$user->uid` null guard → `RuntimeException` (real bug fix), `@throws Exception|TypeError`
- `generateSignedAuthCookie()`: `@return array{string, int}`, `@throws TypeError|UnexpectedValueException`
- `destroyAuthentication()`: `@throws Exception|TypeError`, `session_status()` guard (real bug fix)
- `getData()`: `@return ?array<string, mixed>`, `@throws TypeError`
- Added imports: `RuntimeException`, `TypeError`
- **13 tests** in `AuthCookieTest.php`

#### 1F. `CookieManager.php` — ✅ DONE (commit `866e3545eb`)

- `headers_sent()` guard — prevents no-op `setcookie()` calls after headers sent (real bug fix + eliminates PHPUnit warnings)
- Removed dead PHP ≤7.2 `else` branch (we run PHP 8.3)

#### 1G. `AuthenticationTrait.php` — ✅ DONE (commit `866e3545eb`, cascade)

- `logout()`: `@throws Exception|TypeError` (cascade from `destroyAuthentication()`)

#### 1H. `Team::render()` — ✅ DONE (commit `866e3545eb`, −1 baseline entry, real bug fix)

- `empty($data)` → `$data === null` — distinguishes "not provided" from "empty array"
- `foreach ($data ?? [] as $team)` — null-safe iteration
- **Bug**: user with 0 teams caused `foreach(null)` PHP warning in production path

---

### Phase 2: DataAccess Layer (59 errors) — ✅ DONE (commit `61853c67b1`)

**Why:** Completes the entire `Model/DataAccess/` foundation. Every DAO inherits `AbstractDao`.

**59 baseline entries eliminated** (5,293 → 5,234).

#### 2A. `DaoCacheTrait.php` + `AbstractDao.php` — ✅ DONE

- `@throws Exception` on `_cacheSetConnection()` — propagates to all cache-init callers
- `get('1')` int→string — Redis `get()` requires string key
- Null guard in `_getFromCacheMap` — `$keyMap` can be null on cache miss
- `(bool)` casts on `del()` — Redis returns int, trait declares bool
- Null guard for `$keyMap` in `_deleteCacheByKey` — prevents null array access
- Typed `_serializeForCacheKey` param: `array<int|string, scalar|null>`
- Removed phantom `@template T` from `_getFromCacheMap`/`_setInCacheMap` (used `list<mixed>`)
- `@throws Exception` on `_removeObjectCacheMapElement`/`_deleteCacheByKey`
- `_destroyObjectCache` → best-effort try/catch (cache failure is non-critical; TTL handles recovery)
- `@throws PDOException` on `updateFields`
- Cascade `@throws` added to: SessionTokenStoreHandler, SegmentDisabledTrait, Pager, ProjectDao, JobDao, SegmentMetadataDao, SegmentTranslationDao, CustomPayableRateDao, XliffConfigTemplateDao, SetTranslationController

#### 2B. `ShapelessConcreteStruct.php` — ✅ DONE (−1 entry)

- `@implements ArrayAccess<string, mixed>` — fixes generics error
- 3 remaining `@throws DomainException` entries kept in baseline (ArrayAccessTrait cascade risk)

#### 2C. `AbstractDaoObjectStruct.php` — ❌ CANCELLED

- Adding `@throws DomainException` on constructor cascades to ALL struct instantiations (+115 entries)
- Kept as 1 baseline entry — will fix when all struct callers are targeted

#### 2D. `XFetchEnvelope.php` — ✅ DONE (−1 entry)

- `list<mixed>` param type on `$value`

#### Key Decisions (Phase 2)

- **`_destroyObjectCache` made best-effort**: Cache invalidation failure is non-critical. Prevents massive cascade to 25+ DAO methods.
- **Removed phantom templates**: Template T was unreferenced in `_getFromCacheMap`/`_setInCacheMap` parameters. Replaced with `list<mixed>`.
- **`list<mixed>` for cache values**: DaoCacheTrait stores diverse data. `list<mixed>` is honest; callers do instanceof filtering.
- **ArrayAccessTrait `@throws` NOT added**: Used by 11 classes; creates unacceptable cascade.

---

### Phase 3: Utility Layer (88 errors) — ✅ DONE

**Why:** `CatUtils` and `Utils` are called from everywhere. Typing them enables cascade fixes across the entire codebase.

#### 3A. `CatUtils.php` — ✅ DONE (commit `23b20c1867`, −54 entries + 53 new tests)

All 54 errors eliminated. Native param/return types, array shape PHPDocs, null guards, 53 new tests in `CatUtilsTest.php`.

#### 3B. `Utils.php` — ✅ DONE (commit `3b650fbf4e`, −34 entries)

All 34 errors eliminated. Native param types, array shape PHPDocs, guards, 12 new DB-dependent tests.

---

### Phase 4: Worker Cluster (145 errors) — ✅ DONE

**Why:** Prepares for TMAnalysisWorker concurrency hardening. These workers run as daemons and process the highest-volume workloads.

#### 4A. `GetContributionWorker.php` — ✅ DONE (commit `18866124c8`, −54 entries including cascade)

54 baseline entries eliminated (41 direct + 13 cascade from `GetContributionRequest` return type fixes). Key changes:
- `GetContributionRequest::getJobStruct()` → non-nullable `JobStruct` (always does `new JobStruct(...)`)
- `GetContributionRequest::getUser()` → non-nullable `UserStruct`
- `GetContributionRequest::getProjectStruct()` → non-nullable `ProjectStruct`
- `GetContributionRequest::getContexts()` → new `ContributionContexts` value object (replaces untyped `(object)` cast)
- `process()`: proper `instanceof QueueElement` narrowing instead of `@var` annotation
- Native param types on `_formatConcordanceValues(string, string, array)`, `_sortByLenDesc(string, string)`, `issetSourceAndTarget(array)`, `_publishPayload(... string $targetLang, bool $isCrossLang)`
- Array shape PHPDocs on all methods (`array<string, mixed>`, `array<int, array<string, mixed>>`, `array<string, string>`)
- `@throws TypeError` propagation on `process()` and `_execGetContribution()`
- **Bug fix**: `$queueElement` undefined variable in `_getMatches()` → replaced with `$contributionStruct->mt_qe_workflow_parameters`
- Null guard on `SegmentTranslationDao::findBySegmentAndJob()` result
- Null guard on `TmKeyStruct::$key` in `_extractAvailableKeysForUser()`
- `preg_replace` null-safety: `?? $fallback` for all `preg_replace` calls that can return null
- Removed unnecessary `??` on non-nullable properties (`tm_keys`, `mt_quality_value_in_editor`)
- Fixed `@var $tm_key MemoryKeyStruct` invalid PHPDoc → typed closure `TmKeyStruct $tm_key`
- Fixed `@return array[string => string]` invalid PHPDoc → `@return array<string, string>`
- Removed `$jobStruct?->` nullsafe operator (unnecessary after non-nullable return type)
- 26 new tests (10 GetContributionRequest + 16 GetContributionWorker)

#### 4B. `FastAnalysis.php` — ✅ DONE (commit `a21971d0a2` + `4c8b466ad1`, −42 entries + daemon fix)

42 baseline entries eliminated + 1 non-baselined daemon error fixed. Key changes:
- `requireQueueHandler()` helper — eliminates 12 `method.nonObject` errors from nullable `?AMQHandler`
- `instanceof MyMemory` narrowing — proper type-safe engine access for `fastAnalysis()`
- `instanceof Database` guard for `ping()` — `IDatabase` lacks the method
- Native param types on `_updateProject(int, string)`, `_fetchMyMemoryFast(int)`, `_getSegmentsForFastVolumeAnalysis(int)`, `_executeInsert(array, array)`, `_getWordCountForSegment(array, array)`
- Array shape PHPDocs for properties (`$segments`, `$segment_hashes`, `$actual_project_row`)
- `@throws PDOException` on `_checkDatabaseConnection()`
- `@throws RuntimeException` on `cleanShutDown()`
- `@throws LogInvalidArgumentException` on `_checkDatabaseConnection()`, `_executeInsert()`, `_getQueueAddressesByPriority()`, `cleanShutDown()`
- `date_create()` → `new \DateTime()` (cannot return false)
- `is_null(int)` → `!== 0` for `AppConfig::$INSTANCE_ID`
- `(int)$id_job` cast for `MetadataDao::get()` calls
- Null guard for `$pid = $projectStruct->id` (nullable `?int`)
- `$queueInfo` null check before queue operations
- `rpush()` wraps value in array as Predis requires
- Fixed `AbstractEngine::syncMemories()` PHPDoc: `array<string, mixed>|null` → `list<array<string, mixed>>|null`
- `array_values()` for `MyMemory::fastAnalysis()` list param
- PSR-3 context array wrapper for `$projects_list` in logger calls
- `AbstractEngine::class`/`MyMemory::class` template hints for `EnginesFactory::getInstance()`
- Daemon entry: guard `getenv()` return before `realpath()`

#### 4C. `TMAnalysisWorker.php` — ✅ DONE (commit `acc3c74c74`, −55 entries)

55 of 56 errors eliminated. Key changes:
- `MatchesComparator` trait: typed params, return types, null guards
- `ProjectWordCount` trait: all 10 errors fixed via `@throws`, array shapes
- TMAnalysisWorker itself: null guards, typed properties, removed dead code
- 1 residual entry: EnginesFactory `argument.templateType` — kept (needs arch change)

---

### Phase 5: High-Value Controllers (~560 errors) — ✅ DONE

**Why:** Highest-error-count controllers in the codebase. Fixing these creates maximum baseline reduction per commit.

#### 5A. `NewController.php` — ✅ DONE (commit `e97b092d1e`, −86 entries)

All 86 errors eliminated (1 residual fixed via CatUtils param widening). Key changes:
- `buildProjectStructure()`: `@throws TypeError|DomainException`, typed `array<string, mixed>` params
- `$owner`/`$id_customer`: `$user->email ?? ''` (nullable email → non-nullable property)
- `$only_private`: `(int)(...)` cast (bool → int property)
- `validateTheRequest()`: `@return array<string, mixed>`, all 16 `string|false` call-site normalizations via `?: null`/`?: ''`/`(int)` casts
- `validateEngines()`: uid null guard `?? throw new TypeError(...)`, `@throws TypeError`, template type fix
- `validateSubject()`: native param type `string|false|null`
- `validateSourceLang()`/`validateTargetLangs()`: native param types, `?: null`/`?: ''` for explode
- `validatePayableRateTemplate()`: uid null guard, `(int)` cast on template_id
- `validateFiltersExtractionParameters()`/`validateXliffParameters()`/`validateMTQEParametersOrDefault()`/`validateMTQEPayableRateBreakdownsOrDefault()`: uid null guards, `@throws TypeError`, null guards on `->rules`/`->params`/`->breakdowns`
- `validateMetadataParam()`: native param type `?string`, ternary for json_decode fallback
- `generateTargetEngineAssociation()`: native param types, removed null from return type
- `sanitizeTmKeyArr()`/`parseTmKeyInput()`: native param types, typed returns
- `validateTeam()`: narrowed return to non-nullable `TeamStruct`, `(int)` cast on `$id_team`
- `validateQaModelTemplate()`/`validateQaModel()`: native param types, `(int)` cast on ids
- `create()`: `get_object_vars()` for UploadElement iteration, `AbstractEngine::class` template arg
- `validateTmAndKeys()`: `preg_replace ?? ''` null safety, `get_object_vars()` for UploadElement, `?->` null-safe for TmKeyStruct
- 40 new tests (validation methods) + 6 UploadElement tests
- CatUtils::sanitizeOrFallbackProjectName param widened to `array<array-key, array<string, mixed>>`

#### 5B. `CreateProjectController.php` — ✅ DONE (commit `e97b092d1e`, −76 entries)

All 76 errors eliminated. Same patterns as NewController (independent implementations):
- `buildProjectStructure()`: `@throws TypeError|DomainException`, typed params
- `$id_customer`/`$owner`: `$user->email ?? ''`
- `validateTheRequest()`: `@return array<string, mixed>`, `$file_name ?: ''`, `(int)$due_date`
- `validateMtEngine()`: uid null guard, template type fix, `@throws TypeError|InvalidArgumentException`, typed return `array{mt_engine: int, engine: AbstractEngine|null}`
- `validateSourceLang()`/`validateTargetLangs()`: native param types, `@throws`
- `validatePublicTMPenalty()`: `@throws InvalidArgumentException`
- `validateMMTGlossaries()`: `@throws InvalidArgumentException`
- `validateQaModelTemplate()`: typed params, `$json ?: '{}'` for JSONValidatorObject, uid null guard
- `validatePayableRateTemplate()`: uid null guard, `@throws TypeError`
- `validateFiltersExtractionParameters()`: typed return `?array<string, mixed>`
- `validateXliffParameters()`: uid null guard, null guard on `->rules`, `@throws TypeError`, typed return
- `appendFeaturesToProject()`: typed return `array<string, mixed>`
- `generateTargetEngineAssociation()`: native param types, non-nullable return
- `setTeam()`: non-nullable return, typed param, `(int)` cast
- `setMetadataFromPostInput()`: removed unused `@throws`, typed param
- `assignLastCreatedPid()`: native param type `int`
- `clearSessionFiles()`: `@throws Exception`
- Properties: `/** @var array<string, mixed> */` on `$data`, `$metadata`
- `getData()`: `@return array<string, mixed>`

#### 5C. `GetContribution + DeleteContribution controllers` — ✅ DONE (commit `a357416ba2`, −71 entries)

71 errors eliminated across GetContributionController and DeleteContributionController.

#### 5D. `CommentController` — ✅ DONE (commit `852398bf5c`, −79 entries)

79 errors eliminated.

#### 5E. `GetSearchController` — ✅ DONE (commit `8a2714cbe2`, −68 entries)

68 errors eliminated.

#### 5F. `UploadHandler` — ✅ DONE (commit `a87bdf12ca`, −42 entries)

42 errors eliminated.

#### 5G. Residual fixes after develop merge — ✅ DONE (commit `ac74eaa9f0`, −20 entries)

20 entries fixed (regressions from merge + stale entries).

#### 5H. `AIAssistantController + MultiCurlHandler` — ✅ DONE (commit `2c9f4cdde0`, −26 entries)

26 errors eliminated.

---

### Phase 6: Models & Modules (~244 errors) — ✅ DONE

**Why:** These modules are self-contained subsystems with high error density. Each can be fixed independently.

#### 6A. `TeamModel` — ✅ DONE (commit `a4a40e1dff`, −37 entries)

37 errors eliminated. Typed params and returns across team management methods.

#### 6B. `FilesStorage module` (IFilesStorage, AbstractFilesStorage, FsFilesStorage, S3FilesStorage) — ✅ DONE (commit `9580171b5f`, −109 entries)

109 errors eliminated. Full PHPDoc with `@throws` annotations, typed contracts across the entire interface/abstract/concrete hierarchy.

#### 6C. `TmKeyManagement module` (8 files + EngineConstants) — ✅ DONE (commit `ad8b0ca30c`, −66 entries)

66 errors eliminated. Key changes:
- TmKeyStruct: null guards in `getCrypt()`/`isEncryptedKey()`, typed `__set`, constructor uses `get_object_vars` instead of unsafe foreach-on-object
- TmKeyManager: `filter_var` type safety with explicit `!== false` guard, null-safe `array_shift`, `filterOutByOwnership` nullable email parameter
- EngineConstants: `@return` fixed from `AbstractEngine[]` to `array<class-string<AbstractEngine>, class-string<AbstractEngine>>`

#### 6D. `Translators module` — ✅ DONE (commit `3090ce5b46`, −32 entries)

32 errors eliminated. TranslatorsModel: typed params and returns across translator management methods.

---

### Security Fixes (VULN-02 through VULN-05)

| # | Scope | Commit |
|---|-------|--------|
| VULN-02 | Reject falsy MIME type in upload allowlist check — empty string bypassed validation | `a35d408b7d` |
| VULN-03 | Remove open redirect via unused `redirect` parameter in upload form | `fb8f1836a9` |
| VULN-04 | Use canonical host constant instead of client-supplied `HTTP_HOST` in redirect URLs | `882098c6ec` |
| VULN-05 | Cap `php://input` read buffer to 500MB to prevent memory exhaustion DoS | `50b5d54dd6` |

---

## Key Architectural Improvements

1. **Native return types** on AbstractEngine methods — constructor, `__get`, `__set`, `_decode`, `getCurlFile`
2. **Null guards** using `?? throw new Exception(...)` pattern throughout
3. **`@phpstan-assert`** postcondition annotations on validation methods
4. **Typed properties** on AbstractDaoObjectStruct (`$cached_results`)
5. **Removed dead code** and invalid inline `@var` tags
6. **Singleton non-nullable return** (`OauthTokenEncryption::getInstance()`)
7. **`is_array()` guards** before `array_key_exists()` on mixed-type struct fields
8. **`ActivityLogStruct::$ip` → `?string`** — cascade fix across 10 files
9. **`AuthCookie::setCredentials()`** — null guard on `$user->uid` with `RuntimeException` (real bug: unauthenticated user could reach this path)
10. **`CookieManager::setCookie()`** — `headers_sent()` guard + removed dead PHP ≤7.2 branch
11. **`AuthCookie::destroyAuthentication()`** — `session_status()` guard (real bug: `session_destroy()` on uninitialized session)
12. **`Team::render()`** — `empty($data)` → `$data === null` + `?? []` guard (real bug: user with 0 teams caused `foreach(null)` warning in production)
13. **FilesStorage interface** — full PHPDoc with `@throws` annotations, typed contracts across IFilesStorage/AbstractFilesStorage/FsFilesStorage/S3FilesStorage
14. **TmKeyStruct** — null guards in `getCrypt()`/`isEncryptedKey()`, typed `__set`, constructor uses `get_object_vars` instead of unsafe foreach-on-object
15. **TmKeyManager** — `filter_var` type safety with explicit `!== false` guard, null-safe `array_shift`, `filterOutByOwnership` nullable email parameter
16. **EngineConstants** — `@return` fixed from `AbstractEngine[]` to `array<class-string<AbstractEngine>, class-string<AbstractEngine>>`
17. **Full engine hierarchy** — native types across MMT, MyMemory, Lara, 7 sibling engines, Results classes, EnginesFactory, and validators
18. **DaoCacheTrait** — `_destroyObjectCache` made best-effort (cache failure non-critical), phantom `@template T` removed, typed cache values as `list<mixed>`
19. **GetContributionWorker** — `ContributionContexts` value object replaces untyped `(object)` cast, `GetContributionRequest` typed accessors
20. **TranslatorsModel** — typed params and returns across translator management methods

---

## Coverage & Test Suite Health

Measured with: `vendor/bin/phpunit --exclude-group=ExternalServices --coverage-text`  
Driver: Xdebug 3.5.0, PHP 8.3.31, PHPUnit 12.5.25

| Metric | Value |
|--------|-------|
| **Total tests** | 5,754 |
| **Assertions** | 15,784 |
| **Warnings** | 0 |
| **Status** | ALL PASSING |

### Coverage Analysis

- **Class coverage more than tripled** (8.48% → 28.38%) — 142 additional classes now have test coverage.
- **Method coverage nearly tripled** (21.74% → 57.22%) — 1,529 additional methods covered.
- **Line coverage grew by +38.09%** (21.19% → 59.28%) — 13,644 additional lines covered.
- **Tests grew by 3,506** (2,248 → 5,754) — +155.9% test count.
- **PHPStan: 0 errors** on full codebase — baseline-referenced only. 2,286 remaining entries.

---

## Known Issues

- **FiltersConfigTemplateDao::getByUidAndName()** uses wrong hydration class — documented in `.sisyphus/drafts/filters-config-template-dao-wrong-hydration-class.md`
- **develop branch fatal error**: `FeatureSet` missing abstract methods from subfiltering interface change — coverage run required submodule sync
- **1 unfixable PHPStan error**: `argument.templateType` in TmKeyManagementController — caused by `EnginesFactory::getInstance()` generic template type (known PHPStan limitation with abstract factory patterns)

---

### Phase 7: Revision Feature Foundation (~24 errors) — ✅ DONE

**Why:** `AbstractRevisionFeature` is the abstract base for all revision/review features. Fixing it propagates type safety to `ReviewExtended`, `SecondPassReview`, and all review controllers.

#### 7A. `AbstractRevisionFeature.php` — ✅ DONE (commit `c5ff0d18fc`, −24 entries net, +30 tests)

All in-file PHPStan errors eliminated. Key changes:
- **Bug fix**: `get_called_class() instanceof ReviewExtended` always evaluated to `false` (class-string is not an object) → replaced with `is_a(static::class, ReviewExtended::class, true)`
- **Bug fix**: `file_get_contents()` return value unchecked (`string|false` → `json_decode(string)`) → added `=== false` guard with `RuntimeException`, suppressed redundant PHP warning via `@`
- **Bug fix**: `findChunkReviews(...)[0]` accessed on potentially empty array → added `?? null` null-coalescing
- **Null guards**: `ProjectDao::findById()` result (×4 call sites), `$chunk->id` (×1), `$chunk_review->review_password` (×1), `$job->id` (×1), `$job->password` (×1)
- **Removed dead code**: `isset()` on non-nullable `$projectStructure->features` (always `array`) and `$projectStructure->create_2_pass_review` (always `bool`)
- **Type annotations**: `@throws` additions (TypeError, RuntimeException, PDOException, DomainException, Exception), typed `$undo_data` param as `array<string, mixed>`, typed `$options` as `array{source_page?: int, first_record_password?: string|null}`, typed return as `ChunkReviewStruct[]`, typed `$dependencies` as `list<string>`
- **1 cascade entry added**: `ReviewsController::createReview()` (calls `createQaChunkReviewRecords` which now `@throws TypeError`)
- **30 new tests** in `AbstractRevisionFeatureTest.php` (81% line coverage, 0 warnings)

#### 7B. `ReviewedWordCountModel.php` + `TransactionalTrait.php` — ✅ DONE (commit `d4c46f4bc5`, −38 entries, +18 tests)

All in-file PHPStan errors eliminated across 26 baseline entries (45 total occurrences). Key changes:
- **Null guards**: Constructor throws `RuntimeException` when `TranslationEvent::getChunk()` or `getSegmentStruct()` returns null; cached `$_segment` property eliminates repeated nullable DB calls (7 occurrences)
- **Type narrowing**: `$_chunk` property changed from `?JobStruct` to `JobStruct` (eliminates 14 property.nonObject + method.nonObject occurrences)
- **Argument.type fixes**: Inline `?? throw new RuntimeException(...)` at 5 call sites (`$_chunk->id`, `$_chunk->password`, `$revision->review_password`, `$issue->id`); null-coalesce for `eq_word_count ?? 0.0` and `translation ?? ''`
- **TransactionalTrait**: `private static $__transactionStarted` → `protected static` (eliminates `staticClassAccess.privateProperty` ×5 in THIS file + ×15 in 3 other users: TranslationEventsHandler, TranslatorsModel, MetadataDao)
- **Type annotations**: `@throws PDOException` on all 3 trait methods, `@throws RuntimeException` on constructor/deleteIssues/flagIssuesToBeDeleted, typed `$_finalRevisions` as `TranslationEventStruct[]`, `$_sourcePagesWithFinalRevisions` as `int[]`, `$chunkReviews` param as `ChunkReviewStruct[]`, `$finalRevisions` as `TranslationEventStruct[]`, `$chunkReviewsWithFinalRevisions` as `array<int, ChunkReviewStruct>`
- **Performance**: `getSegmentStruct()` was a DB query per call (7 calls → 1 cached)
- **18 new tests** in `ReviewedWordCountModelTest.php` (85% line coverage, 0 warnings)

---

### Phase 8: Controllers & Traits (~36 entries) — ✅ DONE

**Why:** `SegmentAnalysisController` is a high-traffic API endpoint consumed by the frontend analysis panel. Fixing it ensures type-safe segment data formatting, proper null guards on DB lookups, and correct exception propagation.

#### 8A. `SegmentAnalysisController.php` + `SegmentDisabledTrait.php` — ✅ DONE (commit `4d23170dbc`, −36 entries, +13 tests)

All in-file PHPStan errors eliminated (29 baseline entries + 4 cascade from `@throws DivisionByZeroError` propagation + 1 `SegmentDisabledTrait` bug fix + 2 `missingType.checkedException` on trait). Key changes:
- **Null guard**: `JobDao::getByIdAndPassword()` result in `formatSegment()` → `?? throw new RuntimeException('Job not found')`
- **Null assertions**: `$jobStruct->id ?? throw new RuntimeException(...)` and `$jobStruct->password ?? throw new RuntimeException(...)` before passing to `SegmentDao`
- **Type cast**: `getMetadataValue()` (`mixed`) → `!empty(...)` for clean `bool` to `MatchConstantsFactory::getInstance(?bool)`
- **Null coalesce**: `CatUtils::getSegmentTranslationsCount() ?? 0` — method returns `?int`
- **Type assertion**: `assert($filter instanceof MateCatFilter)` after `MateCatFilter::getInstance()` (vendor returns `AbstractFilter`)
- **Removed misplaced `@var`**: `/** @var MateCatFilter $filter */` was above `$jobStruct` assignment (different variable)
- **Array shape PHPDocs**: all 13 `missingType.iterableValue` errors resolved with precise shapes
- **Native types**: `humanReadableSourcePage(int $sourcePage)`, `getIssuesNotesAndIdRequests(array $segmentsForAnalysis)`
- **`@throws` annotations**: `DivisionByZeroError`, `Exception`, `PDOException` propagation on `job()`, `project()`, `getSegmentsForAJob()`, `getSegmentsForAProject()`, `getIssuesNotesAndIdRequests()`, `destroySegmentDisabledCache()`
- **Bug fix** (`SegmentDisabledTrait`): `SegmentMetadataDao::get()` returns `?SegmentMetadataStruct` (single struct), not array — removed erroneous `[0]` offset access that would crash on non-null results
- **13 new tests** in `SegmentAnalysisControllerTest.php` (0 warnings)

---

### Phase 9: LQA Stack (~109 entries) — ✅ DONE

**Why:** The LQA (Language Quality Assessment) subsystem handles all QA validation — tag checking, whitespace normalization, DOM analysis, BxEx/G tag validation, size restrictions, ICU pattern checks, and symbol comparison. It spans 19 PHP files with 109 baseline entries.

#### 9A. Full LQA stack — ✅ DONE (−97 entries, 12 residual)

97 of 109 baseline entries eliminated across all 19 files in `lib/Utils/LQA/`. Coverage was already >80% on all files (existing tests from prior sessions). Key changes by file:

**`QA/DomHandler.php`** (24→2): `array<string, mixed>` property types replacing overly strict shapes, `DOMNodeList<DOMNode>` generics, `LibXMLError` param type on `checkUnclosedTag()`, null-narrowing `$this->srcDom`/`$this->trgDom` with explicit check + `DOMException`, `$element->ownerDocument?->saveXML()` null-safe chain, `$node !== null` guard for `textContent`, typed `$TagReference` as `array{id?: string}`, cleaned `queryDOMElement()` return logic.

**`QA/TagChecker.php`** (19→1): `list<string>` for `$tagPositionError`, PHPDoc array types on all private methods (`normalizeTags`, `extractIdAttributes`, `extractEquivTextAttributes`, `checkTagPositionsAndAddTagOrderError`, `checkContentAndAddTagMismatchError`, `checkWhiteSpaces`, `checkDiff`), null-narrowing `getTrgDom()` before `setNormalizedTrgDOM()`.

**`QA/WhitespaceChecker.php`** (11→1): `DOMNodeList<DOMNode>` generics, `$srcDom`/`$trgDom` null checks before `queryDOMElement()`, `$srcNode` null guard before `ownerDocument` access, `mb_split()` false-guard in `checkHeadCRNL`/`checkTailCRNL`, `preg_replace` fallback in `nbspToSpace()`.

**`QA/ErrorManager.php`** (10→1): `array<int, string|null>` for `$errorMap`/`$tipMap`, `array{ERROR: list<ErrObject>, WARNING: list<ErrObject>, INFO: list<ErrObject>}` for `$exceptionList`, `json_encode() ?: '[]'` on all JSON methods, typed `$errorMap` param as `array{code: int, debug?: string|null, tip?: string|null}`, string-cast for `$errorCount` offset lookup.

**`QA.php`** (9→2): Return type PHPDocs for `getMalformedXmlStructs()` and `getTargetTagPositionError()`, `@throws Exception` on `prepareDOMStructures()`, null-narrowing on DOMDocument accesses.

**`PostProcess.php`** (9→1): `preg_replace` null-safety fallbacks, `mb_strlen`/`mb_substr` null-coalesce on inputs, DOMDocument null checks, strict comparison fix.

**`QA/ContentPreprocessor.php`** (8→2): `preg_replace_callback` null-safety, `replaceAscii()` string|false narrowing, static property type remains as residual (PHPStan literal-type limitation).

**`BxExG/Mapper.php`** (5→0): `$childNode` null guard before `->nodeName` access.

**`BxExG/Validator.php`** (2→0), **`BxExG/Element.php`** (2→0), **`QA/ErrObject.php`** (2→0), **`QA/SymbolChecker.php`** (1→0), **`SizeRestriction/SizeRestriction.php`** (4→2), **`SizeRestriction/EmojiUtils.php`** (2→0), **`SizeRestriction/CJKLangUtils.php`** (1→0), **`ICUSourceSegmentChecker.php`** (1→0): PHPDoc annotations, null guards, and type narrowing.

**12 residual entries** — hard-to-fix structural issues:
- `ContentPreprocessor::$asciiPlaceHoldMap` static property type vs literal (PHPStan limitation)
- `CheckTagPositionsEvent` constructor expects `bool`, receives `int` (upstream class contract)
- `SizeRestriction` nullable property chains through `preg_replace` (11 occurrences)
- Various `string|false`/`string|null` from DOM/regex operations in deeply nested flows

---

## Aligner Plugin (Deferred)

737 errors across 11 files in `plugins/aligner/`. Separate module — to be addressed as a dedicated batch if time permits.

---

### Phase 10: Outsource Provider (~31 errors) — ✅ DONE

**Why:** `Translated.php` is the sole outsourcing integration, consumed by `OutsourceToController`. Fixing it ensures type-safe vendor API communication, correct `http_build_query` encoding, and proper null guards on session-cached cart data.

#### 10A. `Translated.php` — ✅ DONE (−31 entries, +8 tests)

All in-file PHPStan errors eliminated. Key changes:

- **`http_build_query` bug fix**: `PHP_QUERY_RFC3986` was passed as `$numeric_prefix` (2nd arg) instead of `$encoding_type` (4th arg) — keys would be prefixed with `1` instead of nothing (2 sites)
- **String division fix**: `$this->fixedDelivery / 1000` on a `string` property → added `(int)` cast
- **`json_encode` false guard**: added `RuntimeException` on encoding failure in `__getProjectData`
- **`FeatureSet` null guard**: added `RuntimeException` when `$this->features` is null before `Status` construction
- **`Cart::getItem` null guard**: `__updateCartElements` now throws `RuntimeException` if cart item not found (was silently using null as array)
- **`strrpos` false guard**: `__addCartElementToCart` now throws `RuntimeException` on malformed cart element IDs
- **`__prepareOutsourcedJobCart` null return**: added `continue` guard before `__addCartElement` when no lang pairs found
- **`$_quote_result` array wrapping**: removed extra `[$cartElem]` wrapping — was `list<ItemHTSQuoteJob>` instead of `AbstractItem`
- **`__updateCartElements` signature**: changed `int $newTimezone` to `string` (matches `AbstractProvider::$timezone` type)
- **`getLangPairs` signature**: widened `int $jid` to `int|string` (callers pass `explode()` result)
- **`static::$OUTSOURCE_URL_CONFIRM`** → `self::` (private property, 2 sites)
- **21 PHPDoc annotations**: `@param array<string, mixed>`, `@return`, `@throws` across all methods
- **8 new tests** in `TranslatedTest.php` (pure function tests + behavioral guard tests, 0 warnings)

---

### Phase 11: CI Test Infrastructure — ✅ DONE

**Why:** 4 tests in `CommentControllerTest` and `GetContributionControllerTest` passed locally (seeded DB) but failed in CI (fresh DB from `tests/inc/unittest_matecat_local.sql`). The CI seed only contains 1 user (`uid=1886428310, email='domenico@translated.net'`), missing the `foo@example.org` user that `UserDao::getProjectOwner()` resolves via `JOIN users.email = jobs.owner`.

#### 11A. Self-Contained Test Data — ✅ DONE (commit `b3b34bc321`)

Made tests independent of local DB state by inserting required seed data in `setUp()` within transactions (rolled back in `tearDown()`). No baseline reduction — pure CI reliability fix.

**`GetContributionControllerTest.php`** (2 tests fixed):
- Added `Database::obtain()->begin()` in `setUp()` + `rollback()` in `tearDown()`
- `INSERT IGNORE INTO users` — fake user `foo@example.org` (uid 1886472050) for `getProjectOwner()` resolution
- Tests fixed: `get_concordance_search_returns_valid_response`, `get_segment_contribution_returns_valid_response`

**`CommentControllerTest.php`** (2 tests fixed):
- `INSERT IGNORE INTO users` — same fake user for `resolveUsers()` project-owner resolution
- `INSERT IGNORE INTO teams` — team 32786 for `resolveTeamMentions()` 
- `INSERT IGNORE INTO teams_users` — membership (uid 1886428336) for team member resolution
- `INSERT IGNORE INTO jobs` — job 1886428342 (password `92c5e0ce9316`, project 1886428330) for `resolveTeamMentions` test path
- Tests fixed: `resolveUsers_includes_contributors_and_owner`, `resolveTeamMentions_with_valid_team_resolves_members`

**Key design decisions:**
- Used `INSERT IGNORE` to avoid conflicts when running locally (where data may already exist)
- Inserted minimal data: user + team + membership + job — no over-seeding
- Transaction begin/rollback pattern consistent with existing `CommentControllerTest` conventions
- All 51 tests in both files verified passing with 0 warnings

---

### Phase 12: Tier 1 Easy Wins + DI Refactor (~70 errors) — ✅ DONE

**Why:** Highest ROI batch — mostly PHPDoc-only fixes across 8 files, plus a targeted DI refactor on Chunk V3 to unlock testability.

#### 12A. Tier 1 PHPDoc Batch — ✅ DONE (commit `f2540750cb`, −44 baseline entries, +65 tests)

| File | Errors Fixed | Coverage Before → After | Notes |
|------|-------------|------------------------|-------|
| `Utils/Logger/MatecatLogger.php` | 19 | 0% → 100% | Pure PHPDoc (`array<string, mixed>` context params + `@throws`) |
| `View/App/Json/Analysis/AnalysisFile.php` | ~8 | 100% (existing) | Typed constructor params, `@throws TypeError`, array shapes |
| `View/App/Json/Analysis/AnalysisFileMetadata.php` | ~2 | 100% (existing) | Return type fix |
| `View/V2/Json/Membership.php` | 9 | 0% → 100% | Removed dead `is_null()` guard, typed returns |
| `Utils/Email/MembershipCreatedEmail.php` | 5 | 0% → 100% | `$this->title ?? ''` for nullable-to-string, `@throws` |
| `Utils/Email/MembershipDeletedEmail.php` | 3 | 0% → 100% | Same pattern as above |
| `View/V3/Json/Chunk.php` | 12 | 20% → 88% | DI refactor (constructor-injected `JobDao`/`ChunkReviewDao`), extracted `renderQualitySummary()` |
| `TranslationEventDao.php` | 12 | 0% → 100% | PHPDoc + `?? null` → `?: null` fix; integration tests |
| **Total** | **70** | — | — |

Key architectural changes:
- **Chunk V3 DI refactor**: Added constructor with optional `?JobDao` and `?ChunkReviewDao` (defaults to `new`). Zero breaking change — all existing `new Chunk()` call sites continue to work.
- **Extracted `renderQualitySummary()`**: Protected method wrapping `QualitySummary` instantiation — enables test isolation without touching deeply-coupled QualityReport stack.
- **TranslationEventDao integration tests**: `#[Group('PersistenceNeeded')]` — run in standard suite, follow `TranslationVersionDaoTest` pattern exactly.

New test files:
- `tests/unit/Utils/Logger/MatecatLoggerTest.php` (26 tests)
- `tests/unit/View/API/V2/Json/MembershipTest.php` (7 tests)
- `tests/unit/Utils/Email/MembershipEmailTest.php` (9 tests)
- `tests/unit/View/API/V3/Json/ChunkTest.php` (12 tests)
- `tests/unit/Plugins/TranslationEvents/TranslationEventDaoTest.php` (11 tests)

---

### Phase 13: Quality Report Cluster (~100 errors) — ✅ DONE

**Why:** The Quality Report stack is a tightly coupled domain cluster — controllers, models, structs, validators. Fixing it as a unit ensures consistent typing across the entire QR data flow from DAO through model to API response.

#### 13A. Leaf Structs & Validators (commit `1be0e6a57d`, −15 entries)

| File | Notes |
|------|-------|
| `QualityReportSegmentStruct.php` | DI for MetadataDao (`?MetadataDao $metadataDao = null`), float types for PEE, null guards. Coverage: **100%** |
| `RevisionFactory.php` | `static→self` (no subclasses), restructured `getInstance()`. Coverage: **100%** |
| `AbstractRevisionFeature.php` | Incremental type fixes |
| `IsJobRevisionValidator.php` | DI refactor: constructor accepts `?ChunkReviewDao`. Coverage: **100%** |
| `FilterRevisionChangeNotificationListEvent.php` | Type annotation |

#### 13B. Models (commit `1be0e6a57d`, −49 entries)

| File | Errors Fixed | Coverage | Notes |
|------|-------------|----------|-------|
| `QualityReportSegmentModel.php` | 25→0 | 80% (8/10 methods) | Typed properties, return types, local var narrowing, null guard; DI for ChunkReviewDao |
| `QualityReportModel.php` | 24→0 | 91.45% lines (19/23 methods, 82.61%) | Typed properties, ArrayObject generics, dead code removal, null safety; DI for QualityReportDao, ChunkReviewDao, FeedbackDAO |

#### 13C. Controllers (commit `1be0e6a57d`, −36 entries)

| File | Errors Fixed | Coverage | Notes |
|------|-------------|----------|-------|
| `QualityReportControllerAPI.php` | 21→0 | 80% (8/10 methods) | `createQualityReportModel()` factory method for testability |
| `RevisionFeedbackController.php` | 7→0 | 100% (3/3 methods) | `createFeedbackDao()` factory method |
| `QualityFrameworkController.php` | 5→0 | 100% (3/3 methods) | Type annotations |
| `QualityReportController.php` (Views) | 3→0 | 100% (4/4 methods) | Type annotations |

#### 13D. Test Infrastructure

- **`BaseKleinViewController::render()`**: Throws `RenderTerminatedException` when `AppConfig::$ENV === 'testing'` instead of `die()`. Flow control preserved — `throw` satisfies `never` return type. Avoids touching ~10 view controllers that rely on render-as-flow-control.
- **New `RenderTerminatedException`** class: `lib/Controller/Exceptions/RenderTerminatedException.php`
- **DELETE+INSERT pattern**: Fixed in QualityReportViewControllerTest and QualityFrameworkControllerTest for deterministic test state.

#### Key Architectural Changes

- **DI refactor of QualityReportModel**: Injected `QualityReportDao`, `ChunkReviewDao`, `FeedbackDAO` as constructor params with `= null` defaults. Protected wrappers: `getSegmentsForQualityReport()`, `createRevisionFactory()`, `updateChunkReview()` — wrap static DAO/factory calls so test subclasses can override.
- **DI refactor of QualityReportSegmentModel**: Injected `ChunkReviewDao` as constructor param with `= null` default.
- **DI refactor of IsJobRevisionValidator**: Injected `ChunkReviewDao` as constructor param with `= null` default; test rewritten to use mock DAO.
- **Controller factory methods**: `createQualityReportModel()` in QualityReportControllerAPI, `createFeedbackDao()` in RevisionFeedbackController — minimal production changes enabling mock injection in tests.

#### New Test Files (10 files, 40 tests)

| File | Tests | Assertions |
|------|-------|------------|
| `QualityReportModelTest.php` | 19 | 77 |
| `QualityReportSegmentModelTest.php` | 15 | 52 |
| `QualityReportControllerAPITest.php` | 13 | — |
| `QualityReportViewControllerTest.php` | 6 | — |
| `QualityFrameworkControllerTest.php` | 5 | — |
| `RevisionFeedbackControllerTest.php` | 4 | — |
| `AbstractRevisionFeatureTest.php` | — | — |
| `RevisionFactoryTest.php` | — | — |
| `QualityReportSegmentStructTest.php` | — | — |
| `IsJobRevisionValidatorTest.php` | — | — |

---

## Queue (Next Targets — Priority Order)

### Phase 15: Projects Directory Coverage + Root-Cause Fix (~46 entries) — ✅ DONE

**Why:** Completing `lib/Model/Projects/` — the last 3 files below 80% coverage. Root-cause fix in `AbstractDao::_destroyObjectCache()` eliminated 46 stale baseline entries across the entire codebase in one surgical change.

#### 15A. Root-Cause Fix: `AbstractDao::_destroyObjectCache()` — ✅ DONE (−46 baseline entries)

**Problem:** `LoggerFactory::getLogger()` inside the existing catch block in `_destroyObjectCache()` could throw `Psr\Log\InvalidArgumentException`, which cascaded `@throws` annotations to every DAO method calling `_destroyObjectCache()` (46 baseline entries across MetadataDao, ProjectDao, and 20+ other DAO files).

**Fix:** Wrapped the `LoggerFactory::getLogger()` call in a nested try/catch inside the existing catch block. Logger failure during error recovery is non-critical — silently swallowed. This eliminated ALL 46 cascade entries without touching any downstream files.

**Key decision:** Root-cause fix over cascade `@throws` propagation. Adding `@throws InvalidArgumentException` to MetadataDao/ProjectDao callers would have cascaded to 100+ files. The nested try/catch is architecturally correct: logging failures during error handling should never escape.

#### 15B. Coverage Tests — ✅ DONE (+45 tests, +167 assertions)

| File | Coverage Before → After | Tests | Assertions |
|------|------------------------|-------|------------|
| `ProjectTemplateStruct.php` | 43.06% → **100%** (72/72 lines, 7/7 methods) | 13 | 77 |
| `MetadataDao.php` | 16.22% → **97.30%** (72/74 lines, 7/8 methods) | 11 | 25 |
| `ProjectDao.php` | 6.63% → **92.08%** (186/202 lines, 22/25 methods) | 21 | 65 |
| **Total** | — | **45** | **167** |

New test files:
- `tests/unit/Model/Projects/ProjectTemplateStructTest.php` — struct tests (JSON encoding, serialization, hydration)
- `tests/unit/Model/Projects/MetadataDaoTest.php` — DB integration tests with transaction rollback
- `tests/unit/Model/Projects/ProjectDaoTest.php` — DB integration tests covering 22 of 25 methods (skipped destructive bulk ops)

**Baseline reduction:** 3,206 → 3,160 (−46 entries, −276 lines in `phpstan-baseline.neon`)

---

### Phase 16: QualitySummary View (~17 entries) — ✅ DONE

**Why:** `QualitySummary.php` renders quality report data for the V3 API — the frontend quality summary panel. Fixing it ensures type-safe JSON serialization, proper null guards on nullable job properties, and testable DI for all DAO dependencies.

#### 16A. PHPDoc + Type Fixes + DI Refactor — ✅ DONE (−17 baseline entries, +18 tests)

| File | Errors Fixed | Coverage Before → After | Notes |
|------|-------------|------------------------|-------|
| `QualitySummary.php` | 17→0 | low → **96.58%** (141/146 lines, 6/11 methods) | DI refactor, null guards, PHPDoc shapes |

Key changes:
- **Null guards**: `$jStruct->id` and `$jStruct->password` guarded with `?? throw new RuntimeException(...)` in both `revisionQualityVars()` and `populateQualitySummarySection()` (4 `argument.type` errors)
- **Type fix**: `$quality_overall` parameter typed as `?string` (was untyped); `$model_version` widened from `int` to `?int` (latent bug — `$model?->hash` returns null when no LQA model)
- **Type fix**: `$passfail` native type widened from `array` to `array|bool` (pre-existing mismatch — `revisionQualityVars` returns `true` when no model)
- **PHPDoc shapes**: 8 `missingType.iterableValue` errors resolved with precise array shapes across all 5 methods
- **`@throws` annotations**: `DomainException`, `Exception`, `PDOException`, `ReflectionException` added to `populateQualitySummarySection()` and `getDetails()`
- **DI refactor**: Converted `private static` methods to `protected` instance methods; added 5 protected factory methods (`createQualityReportDao()`, `createFeedbackDao()`, `createEntryDao()`, `getReviewedWordsCountGroupedByFileParts()`, `createRevisionFeature()`) — zero breaking change, all existing callers unaffected
- **18 new tests** in `QualitySummaryTest.php` (52 assertions, 0 warnings)

**Baseline reduction:** 3,160 → 3,121 (−17 entries from `QualitySummary.php`, −22 lines elsewhere from prior Phase 15 baseline cleanup)

---

### Phase 17: GlossaryWorker.php — ✅ DONE (−18 baseline entries, +16 tests)

#### 17A. Bug Fixes + Type Fixes + DI Refactor — ✅ DONE

| File | Errors Fixed | Coverage Before → After | Notes |
|------|-------------|------------------------|-------|
| `GlossaryWorker.php` | 18→0 | 0% → **97.66%** (209/214 lines) | DI refactor, 2 bug fixes, PHPDoc shapes |

Key changes:
- **Bug fix (L145)**: `delete()` had wrong `@var UpdateGlossaryResponse` — method returns `DeleteGlossaryResponse`; also `$payload['id_job']` (int) now cast to `(string)` for `glossaryDelete()` string parameter
- **Bug fix (L426)**: `update()` match arm `202 => "MyMemory is busy..."` was dead code — inside `>= 300` guard but 202 < 300. Restructured to `$response->responseStatus === 202 || $response->responseStatus >= 300` so 202 is correctly treated as error
- **Type casts**: `(string) $payload['id_job']` added to `get()`, `set()`, `update()` — all `glossaryGet/Set/Update()` expect string idJob
- **Null-safe access**: `formatGetGlossaryMatches()` now uses `$matches['id_segment'] ?? null` instead of direct access on optional key
- **Null-safe access**: `set()` now uses `$payload['term']['metadata']['keys'] ?? []` instead of direct access on optional key
- **PHPDoc shapes**: 9 `missingType.iterableValue` errors resolved with precise array shapes
- **Native types**: `setResponsePayload()` params typed (`string`, `string`, `array`, `array`) — was untyped
- **Template resolution**: `EnginesFactory::getInstance(1, MyMemory::class)` resolves template type `T`
- **DI refactor**: `getMyMemoryClient()` changed from `private` to `protected` for testable subclass override
- **16 new tests** in `GlossaryWorkerTest.php` (72 assertions, 0 warnings)

**Baseline reduction:** 3,121 → 3,103 (−18 entries)

---

### Phase 18: Filters.php + IDto.php — ✅ DONE (−18 net baseline entries, +28 tests)

#### 18A. Interface Fix + Type Fixes + DI Refactor — ✅ DONE

| File | Errors Fixed | Coverage Before → After | Notes |
|------|-------------|------------------------|-------|
| `Filters.php` | 21→0 | 0% → **82.78%** (125/151 lines) | DI refactor, 3 behavioral fixes, PHPDoc shapes |
| `IDto.php` | 1→0 | n/a (interface) | Extended `\JsonSerializable` |

Key changes:
- **Interface fix**: `IDto` now extends `\JsonSerializable` — all 7 implementors already implemented it independently, this formalizes the contract
- **Null guard**: `parse_url()` result guarded with `$parsedUrl['host'] ?? ''` instead of direct offset access on potentially false return
- **Type guard**: `$headers[$id]` guarded with `is_array()` check — `getAllHeaders()` returns `array<string, true|string[]>`, `true` value was being passed to `extractInstanceInfoFromHeaders()`
- **String guard**: `pathinfo_fix()` results guarded with `is_string()` — returns `array|string` but PHPStan can't narrow based on flag value
- **DI refactor**: `sendToFilters()`, `extractInstanceInfoFromHeaders()`, `formatErrorMessage()`, `backupFailedConversion()` changed from `private` to `protected`; added `createMultiCurlHandler()` and `createLogConnection()` factory methods
- **PHPDoc shapes**: 14 `missingType.iterableValue` errors resolved with precise array shapes
- **`@throws` annotations**: Added to `sendToFilters()`, `sourceToXliff()`, `xliffToTarget()`, `backupFailedConversion()`
- **28 new tests** across `FiltersTest.php` (19 tests) and `FiltersSendToFiltersTest.php` (9 tests), 64 assertions
- **3 cascade errors** in `XliffToTargetConverterController.php` (not on ledger) — added to baseline

**Baseline reduction:** 3,103 → 3,085 (−21 removed, +3 cascade added = −18 net)

**Phase 18b — 7 DTO subclasses (algorithm step 9 — collateral file check):**
- `IDto extends \JsonSerializable` made the explicit `implements JsonSerializable` redundant on all 7 DTO classes
- Removed redundant `implements JsonSerializable` from: Dita, Json, MSExcel, MSPowerpoint, MSWord, Xml, Yaml
- Removed redundant `@param` PHPDocs that just repeated native types
- Added `@var list<string>` on all array properties, `@param list<string>` on array setters
- Added `@param array<string, mixed>` on `fromArray()`, `@return array<string, mixed>` on `jsonSerialize()`
- Added `@throws DomainException` on `Yaml::setInnerContentType()` and `Yaml::fromArray()`
- **53 errors resolved**, 7 files added to ledger (179 total)

**Baseline reduction (cumulative):** 3,103 → 3,032 (−71 total: −21 Filters − 53 DTOs + 3 cascade = −71 net)

---

## Queue (Remaining Targets — Priority Order)

### Priority 1–4

| Priority | File | Errors | Rationale |
|----------|------|--------|-----------|
| ~~1~~ | ~~`lib/Plugins/Features/ReviewExtended/ReviewedWordCountModel.php`~~ | ~~26~~ | ✅ Done (Phase 7B) |
| ~~2~~ | ~~`lib/Controller/API/V3/SegmentAnalysisController.php`~~ | ~~30~~ | ✅ Done (Phase 8A) |
| ~~3~~ | ~~`lib/Utils/LQA/` (full stack)~~ | ~~109~~ | ✅ Done (Phase 9A, −97) |
| ~~4~~ | ~~`lib/Utils/OutsourceTo/Translated.php`~~ | ~~31~~ | ✅ Done (Phase 10A) |

**All Priority 1–4 targets completed.**

### Phase 5 Residual Controllers

| File | Errors | Notes |
|------|--------|-------|
| ~~`SetTranslationController.php`~~ | ~~25~~ | ✅ Done (−16 entries, coverage 80.08%) |
| ~~`GetContributionController.php`~~ | ~~26~~ | ✅ Done (previous phase, 98.51% coverage) |

### Phase 5B Contribution Stack

| File | Errors Fixed | Coverage Before → After |
|------|-------------|------------------------|
| `AnalysisBeforeMTGetContributionEvent.php` | 3 | n/a (trivial event class) |
| `SetContributionRequest.php` | 5 | mixed → 88.89% |
| `SetContributionWorker.php` | 23 | 56.52% → 85.44% |
| `SetContributionMTWorker.php` | 6 | 68.97% → 96.88% |
| `GetContributionWorker.php` | 0 (coverage only) | 9.68% → 86.29% |
| **Total** | **37** | **All ≥80%** |

---

### Phase 22: GetWarningController (~17 errors) — ✅ DONE

**Why:** `GetWarningController` is the QA warnings endpoint consumed by the editor for real-time segment validation. Fixing it ensures type-safe request validation, proper null guards on job lookups, and correct `SegmentMetadataDao::get()` usage (single struct, not array).

#### 22A. `GetWarningController.php` — ✅ DONE (−14 baseline entries, +18 tests)

All 17 in-file PHPStan errors eliminated (14 baseline entries removed). Key changes:

- **Root cause fix**: `getChunkAndLoadProjectFeatures()` return type `?JobStruct` → `JobStruct` (never returns null — `ChunkDao::getByIdAndPassword()` throws `NotFoundException`). Added native types `string $id_job, string $password` and `(int)` cast for DAO call. This single fix eliminated 12/17 errors.
- **Bug fix (L159)**: `SegmentMetadataDao::get()[0] ?? null` — `get()` returns `?SegmentMetadataStruct` (single struct), not array. Removed invalid `[0]` offset access.
- **Null guard**: `$chunk->id ?? throw new RuntimeException(...)` — guards nullable `?int` property before passing to `MetadataDao::getSubfilteringCustomHandlers(int)`
- **Null guard**: `$this->icuSourcePatternValidator ?? throw new RuntimeException(...)` — guards trait property after `sourceContainsIcu()` call
- **Type casts**: `(int) $id_job` for `WarningDao::getWarningsByJobIdAndPassword()` and `SegmentDao::getTranslationsMismatches()`; `(int) $characters_counter` for `QA::setCharactersCount(?int)`
- **String normalization**: `(string) filter_var(...)` on `FILTER_UNSAFE_RAW` results (src_content, trg_content, token, logs, characters_counter) — eliminates `string|false` return type ambiguity
- **PHPDoc array shapes**: `validateTheGlobalRequest()` → `array{id_job: string, password: string}`, `validateTheLocalRequest()` → full 9-field shape
- **18 new tests** in `GetWarningControllerTest.php` (52 assertions, 0 warnings)

---

### Phase 23: CattoolController + Decorator Chain (~41 errors) — ✅ DONE

**Why:** `CattoolController` is the main editor view (translate/revise). Its decorator chain (`AbstractDecorator`, `ProjectCompletion/CatDecorator`, `Airbnb/CatDecorator`) sets all template variables for the editor UI. Fixing the full chain ensures type-safe request validation, proper null guards, and correct decorator contracts.

#### 23A. `AbstractDecorator.php` — ✅ DONE (−3 baseline entries)

- Made `$template` constructor parameter required (non-null `PHPTALWithAppend`)
- Added `void` return type to abstract `decorate()` method
- Typed `$template` property as `PHPTALWithAppend` (was untyped)

#### 23B. `DownloadOmegaTOutputDecorator.php` — ✅ DONE (−13 baseline entries)

- Decoupled from `AbstractDecorator` hierarchy — it misused the inheritance (no template, returns `array` not `void`, never called via `appendDecorators()`)
- Added own `AbstractDownloadController $controller` property/constructor
- Typed `decorate()` return as `array<string, array{document_content: string, output_filename: string}>`
- Added return/param types to `createOmegaTZip()` and `getOmegatProjectFile()`
- Fixed optional `pathinfo()` keys with `??` default
- Used null coalescing for tokenizer map lookup (was always-false `== null`)
- Fixed `preg_replace` and `str_replace` null safety

#### 23C. `CattoolController.php` — ✅ DONE (−21 baseline entries)

- Removed dead properties `$id_job`/`$request_password` (set but never read)
- Added array shape return to `validateTheRequest()`
- Fixed all PHPDoc `@var` parse errors (swapped to type-first syntax)
- Added null guards via extracted `$chunkId`/`$chunkPassword`/`$projectId` variables with `?? throw RuntimeException`
- Fixed `team_name` null-safety, typed `searchableStatuses()` return
- Added `@throws` tags for all public methods

#### 23D. `ProjectCompletion/CatDecorator.php` — ✅ DONE (−10 baseline entries)

- Added `instanceof CatDecoratorArguments` null guard with `throw RuntimeException`
- Changed property from `?CatDecoratorArguments` to `CatDecoratorArguments`
- Typed `$stats` as `array<string, mixed>`, added `@throws DivisionByZeroError`
- Used direct property access instead of `{'...'}` syntax for PHPTAL template vars

#### 23E. `Airbnb/CatDecorator.php` — ✅ DONE (−5 baseline entries)

- Same `instanceof` guard pattern as ProjectCompletion
- Typed `$arguments` as `CatDecoratorArguments`, added `@throws` annotations
- Removed `@phpstan-ignore property.notFound`, used direct property access for template vars

#### 23F. Supporting changes

- `PHPTALWithAppend.php`: added 6 `@property` declarations for ProjectCompletion and Airbnb template vars
- `HomeDecorator.php` (aligner): added `: void` return type to `decorate()`; 2 pre-existing errors added to baseline
- Net baseline reduction: **−41 entries** (43 removed, 2 added for pre-existing aligner errors)
- **28 new tests** across 4 test files (68 assertions, 0 warnings)

---

### Phase 24: Static DAO Method Removal (`staticInsertStruct` + `staticUpdateStruct`)

**Goal:** Eliminate `AbstractDao::staticInsertStruct()` and `AbstractDao::staticUpdateStruct()` — migrate all callers to instance `insertStruct()` / `updateStruct()`, then delete the static methods.

#### 24A. `staticInsertStruct` removal — ✅ DONE (−6 baseline entries)

Migrated 13 call sites across 12 files to instance `insertStruct()`:
- **Instance-context** (`$this->insertStruct()` / `$dao->insertStruct()`): TranslatorsModel, GDriveUserAuthorizationModel, MembershipDao, SplitDAO, OutsourceConfirmationController, TeamDao
- **Static-context** (`(new XDao())->insertStruct()`): OAuthSignInModel, SignupModel, TranslationIssueModel, TranslationEventsHandler
- **Test**: InsertOnDuplicateKeyTest updated
- Unified option key: `on_duplicate_fields` → `on_duplicate_update` (matches SQL semantics)
- Renamed internal `$on_duplicate_fields` variables → `$on_duplicate_update` in AbstractDao + Database
- Fixed pre-existing typo: `$datastaticInsertStruct` → `$data` (line 633)
- Removed `staticInsertStruct()` method entirely (zero callers remain)

#### 24B. `staticUpdateStruct` removal — ✅ DONE (−6 baseline entries)

Migrated 22 call sites across 18 files to instance `updateStruct()`:
- **Instance-context** (`$this->updateStruct()` / `$dao->updateStruct()`): ConnectedServiceDao (2), GDriveUserAuthorizationModel, UpdateJobKeysController, HotSwap, EngineDAO
- **Static-context** (`(new XDao())->updateStruct()`): ChunkReviewModel, TranslationIssueModel, AbstractRevisionFeature, ConnectedServicesController, QualityReportModel, ProjectModel, RedeemableProject, SignupModel (3), OAuthSignInModel, ChangePasswordModel, PasswordResetModel (2), SegmentTranslationDao, CreateTeamMembershipTask
- Removed `staticUpdateStruct()` method entirely (zero callers remain)
- Fixed null-safety in `RedeemableProject::redeem()` — narrowed `?ProjectStruct` to local non-null variable, guarded `getEmail()` return
- Added `@throws \TypeError` to `AbstractRevisionFeature::projectCompletionEventSaved()`

#### Summary

- **Net baseline reduction:** −6 entries (2,774 → 2,768)
- **Files modified:** 20 (lib) + 2 (tests) + 1 (internal_scripts)
- **On-ledger files verified clean:** AbstractDao, EngineDAO, ProjectModel, QualityReportModel, HotSwap, AbstractRevisionFeature

---

### Phase 25: DAO Ledger Sweep (all remaining DAOs → clean)

**Goal:** Verify and fix all 28 remaining DAO files not yet on the ledger, add all to ledger.

#### 25A. Already clean (19 files) — added to ledger directly

These files had 0 PHPStan errors without baseline, never formally registered:
ActivityLogDao, AnalysisDao, ChunkCompletionEventDao, ChunkCompletionUpdateDao, ConnectedServiceDao, FileDao, Files/MetadataDao, OutsourceConfirmationDao, RemoteFileDao, ContextGroupDao, SegmentDao, SegmentNoteDao, TMSServiceDao, SegmentTranslationDao, WarningDao, JobsTranslatorsDao, TranslatorsProfilesDao, Users/MetadataDao, WordCounterDao

#### 25B. Fixed — `@throws` annotations (7 files)

| File | Errors Fixed | Type |
|------|:---:|---|
| `FiltersConfigTemplateDao.php` | 4 | `@throws TypeError/PDOException/Exception` |
| `EntryDao.php` | 3 | `@throws Exception/TypeError` |
| `QAModelTemplateDao.php` | 1 | `@throws Exception` |
| `MTQEPayableRateTemplateDao.php` | 3 | `@throws PDOException/Exception` + `remove()` |
| `MTQEWorkflowTemplateDao.php` | 3 | `@throws PDOException/Exception` + `remove()` |
| `CustomPayableRateDao.php` | 4 | `@throws PDOException/TypeError/Exception` |
| `XliffConfigTemplateDao.php` | 2 | `@throws TypeError` |

#### 25C. Fixed — behavioral (2 files)

- **`SplitDAO.php`** (3 errors): Fixed `json_encode()` false/null handling → empty string fallback; removed unused `@throws Exception` from `_validatePrimaryKey()`
- **`RedisReplaceEventDAO.php`** (1 error): Changed property type `Client` → `ClientInterface` (has `@method hgetall` annotation); fixed call casing `hgetAll` → `hgetall`

#### 25D. Cascading errors (algorithm step 6 — off-ledger → added to baseline)

- `FiltersConfigTemplateController::update()` — count 1→2 (new TypeError path)
- `XliffConfigTemplateController::update()` — count 1→2 (new TypeError path)
- `PayableRateController::edit()` — new entry (TypeError from `editFromJSON`)
- `TranslationIssueModel::editFrom()` — new entry (TypeError from `updateStruct`)

#### Summary

- **Net baseline reduction:** −22 entries (2,768 → 2,746) — removed 24, added 2 new
- **Files added to ledger:** 28 (19 already clean + 9 fixed)
- **Ledger total:** 236 → 264 (+28)
- **4,926 tests pass, PHPStan clean**

---

### Phase 26: `Executor.php` + `SignalHandlerTrait.php` — PHPStan clean + tests

**Goal:** Fix all 12 PHPStan errors in `lib/Utils/TaskRunner/Executor.php`, add tests.

#### Errors fixed (12 → 0)

| Error | Fix |
|-------|-----|
| `method_exists()` always true | Removed redundant check — `AbstractWorker` always has `getLogMsg()` |
| `attach()` on null (×1) | Used local `$workerInstance` variable after `instanceof` check |
| `process()` on null (×1) | Added local `$worker` with null guard + `continue` |
| `setContext()` on null (×1) | Same — calls on `$workerInstance` (non-null) |
| `setPid()` on null (×1) | Same |
| `_myProcessExists` missing param type | Added `string $pid` |
| `_readAMQFrame` missing `@throws InvalidArgumentException` | Added annotation |
| `installHandler` missing `@throws RuntimeException` | Added `@throws` to `SignalHandlerTrait::installHandler()` |
| `@var $msgFrame Frame` parse error | Fixed to `/** @var Frame $msgFrame */` |
| `@var array` not subtype of `non-empty-list<string>` | Changed to `non-empty-list<string>` |
| Property assign `object` to `?AbstractWorker` | Added `instanceof AbstractWorker` check + `WorkerClassException` |
| `new static()` unsafe | Changed to `new self()` (no subclasses) |

#### Additional changes

- **`SignalHandlerTrait.php`**: Added `@throws RuntimeException` to `installHandler()` (cascading fix for AbstractDaemon too)
- **Script guard**: Wrapped `Bootstrap::start()` in `class_exists` check for testability; guarded bottom-of-file script execution with JSON argv validation
- **Cascading**: Removed 1 stale `AbstractDaemon::installHandler()` baseline entry

#### Summary

- **Net baseline reduction:** −13 entries (2,746 → 2,733) — 12 Executor + 1 AbstractDaemon
- **Files added to ledger:** 2 (Executor.php, SignalHandlerTrait.php)
- **Ledger total:** 264 → 266 (+2)
- **New tests:** 13 in `tests/unit/TaskRunner/ExecutorTest.php` (33 assertions)
- **4,939 tests pass, PHPStan clean**

---

### Phase 27: AMQHandler + MultiCurlHandler + Cascade Cleanup — ✅ DONE

**Commit:** `ef0c689741` (amended)

#### MultiCurlHandler.php — PHPStan clean (0→0, already clean)

- Fixed 7 pre-existing `missingType.checkedException` errors (PDOException via `@throws`)
- Added `@throws PDOException` to `createResource()`, `setOptionsAndDoRequest()`, `multiExec()`, `updateDataStructure()`
- Added typed array shape on `$resourceHashList` property
- Coverage: **83.45%** (14 tests, pre-existing)
- **0 PHPStan errors** with no baseline

#### AMQHandler.php — PHPStan clean + testable via DI

- Fixed all 10 PHPStan errors (removed from baseline):
  - `@throws PDOException` on `getRedisClient()`, `getRedisClientIfAlreadyConnected()`
  - `@throws Exception` on `push($message)`
  - Fixed `preg_replace()` null safety on 3 call sites
  - Fixed `implode()` param order warning
  - Fixed `mixed` comparison with `?: ''` fallback
- **Constructor DI**: New optional 4th parameter `?StatefulStomp $preconfiguredStomp = null` — when provided, bypasses STOMP connection setup (enables testing without broker)
- **18 new unit tests** in `tests/unit/Utils/ActiveMQ/AMQHandlerTest.php`
  - Coverage: **74.29%** (52/70 lines) — constructor body (~18 STOMP connection lines) requires real broker for 80%+
  - Predis\Client mocks via anonymous class (Predis uses `__call` magic — PHPUnit cannot configure mock methods directly)
- **0 PHPStan errors** with no baseline

#### Cascade Fixes (Step 9 — ledger files)

| File | Error | Fix |
|------|-------|-----|
| `AbstractEngine.php` | `callable(): void` return mismatch | Fixed `@return` type annotation |
| `FastAnalysis.php` | Dead PHPDoc `@var` | Removed stale `@var` on `$this->amqHandler` |
| `AnalysisRedisService.php` | `mixed` property type | Added `/** @var AnalysisProject */` annotation |
| `Executor.php` | `@var` parse error | Fixed PHPDoc syntax |

#### Baseline Surgery (Step 8 — manual, NEVER regenerate)

- **Removed:** 26 entries for AMQHandler (10), cascade fixes (8), ledger file cascade (8) — including stale entries from prior phases
- **Added:** 11 new entries (off-ledger files, pre-existing errors surfaced by cascade fixes)
- **Net:** 2,733 → **2,707** (−26 entries)

#### Test Suite

| Metric | Value |
|--------|-------|
| Tests | **4,981** (was 4,939) |
| Assertions | **16,773** (was 16,481) |
| Status | ALL PASSING |
| PHPStan (full) | **0 errors** |

---

### Phase 28: GDrive Directory — All 5 Files Clean — ✅ DONE

**55 errors across 5 files → 0. Bug fix discovered and fixed.**

| File | Errors | Key Changes |
|------|:------:|-------------|
| `GDriveTokenHandler.php` | 5→0 | Missing param types, `json_encode(true)` flag fix, return guard |
| `GDriveTokenVerifyModel.php` | 2→0 | **Bug**: `false===` vs `?string` → `null===` |
| `GDriveUserAuthorizationModel.php` | 8→0 | Iterable types, `@throws TypeError`, `$token` narrowed to `string` |
| `RemoteFileService.php` | 11→0 | Param/iterable types, `@throws`, json/mime/array guards |
| `Session.php` | 29→0 | 12 iterable types + 8 `@throws` + 5 null guards + 4 arg types |

- **1 bug fix**: `GDriveTokenVerifyModel::validOrRefreshed()` compared `false ===` against `?string` (always `false`) → fixed to `null ===`
- **Import additions**: `use TypeError`, `use UnexpectedValueException`, `use InvalidArgumentException` in 4 files
- **6 behavioral null guards**: `json_encode` failure, `(int)$size`, `(string)$mime`, `$parents[0] ?? ''`, `$copiedFile` null
- **Cascade**: 6 errors in 4 off-ledger files → added to baseline
- **Baseline**: 2,707 → **2,666** (−41)
- **Tests**: 4,979 (+8 new tests: 6 GDriveTokenVerifyModel + 2 RemoteFileService)
- **Assertions**: 16,797

---

### Phase 29: GDrive DI Refactor + Test Coverage Push — ✅ DONE

**Goal:** Add DI to `GDriveUserAuthorizationModel` and `Session` for testability, then write comprehensive tests targeting ≥80% coverage.

#### 29A. GDriveUserAuthorizationModel — DI + 97.78% coverage

**Commits:** 2 (DI refactor + tests)

| File | Changes |
|------|---------|
| `GDriveUserAuthorizationModel.php` | Constructor accepts optional `?ConnectedServiceDao $dao` and `?Google_Client $googleClient`. Private `__collectProperties` → `protected` for testable override. `__updateService`/`__insertService` use `$this->dao ?? new ConnectedServiceDao()` lazy fallback. `__collectProperties` uses `$this->googleClient ?? GoogleProvider::getClient(...)`. Added `use Google_Client` import. |
| `GDriveUserAuthorizationModelTest.php` | **9 tests**, 31 assertions. Coverage: **97.78% lines** (44/45), **80% methods** (4/5). Tests: constructor (4), updateOrCreateRecordByCode with dao mock (update + insert + 2 error paths), collectProperties with Google client mock. Uses `TestableGDriveUserAuthorizationModel` subclass to override `__collectProperties` as no-op and pre-set token/user properties. |

#### 29B. Session — DI + 63.79% coverage

| File | Changes |
|------|---------|
| `Session.php` | Constructor accepts optional `?array &$sessionData`, `?ConnectedServiceDao $dao`, `?AbstractFilesStorage $filesStorage`. When `$sessionData` is null, uses `$_SESSION` (backward compatible). Added `protected createFeatureSet()` and `protected createFilesConverter()` factory methods for testable subclass overrides. `getInstanceForCLI` passes `$session` through constructor instead of mutating `$_SESSION`. `getTokenByUser` uses `$this->dao ?? new ConnectedServiceDao()` lazy fallback. |
| `SessionTest.php` | **56 tests**, 107 assertions. Coverage: **63.79% lines** (155/243), **72.41% methods** (21/29). Tests: constructor (2), getInstanceForCLI (2), hasFiles/sessionHasFiles (3), findFileIdByName (2), clearSession/clearFileListFromSession (3), addFiles (2), setConversionParams, getToken/getTokenByUser (3), getService (3), buildRemoteFile (3), grantFileAccessByUrl (2), createRemoteFile (2), importFile error path (4), removeFile (2), removeAllFiles (2), reConvert (2), getFileStructureForJsonOutput (3), sanitizeFileName (3), deleteDirectory, getCacheFileDir, getGDriveFilePath, getGDriveFilePathForS3, createFeatureSet, createFilesConverter. I/O-heavy class — full 80% blocked by inline FilesConverter(8 params), Google API chains, and filesystem dependencies. |

#### GDrive Coverage Summary

| File | Methods | Lines | Tests |
|------|---------|-------|-------|
| GDriveTokenHandler | 100% (2/2) | 100% (15/15) | 14 |
| GDriveTokenVerifyModel | 100% (5/5) | 100% (22/22) | 6 |
| GDriveUserAuthorizationModel | 80% (4/5) | 97.78% (44/45) | 9 |
| RemoteFileService | 88.89% (8/9) | 98.31% (58/59) | 18 |
| Session | 72.41% (21/29) | 63.79% (155/243) | 56 |

- **Baseline**: 2,666 (unchanged — no baseline modifications, Phase 28 already cleaned all entries)
- **Tests**: 5,079 (+98: +9 GDriveUserAuthorizationModel + 56 Session)
- **Assertions**: 17,186
- **Files added to ledger**: 0 (GDrive files already on ledger from Phase 28)
- **Full GDrive suite**: 98/98 pass, 0 warnings

---

### Phase 30: lib/Model/Jobs/ — Full Directory Cleanup — ✅ DONE

**Goal:** Clean all PHPStan errors from every file in `lib/Model/Jobs/`.

#### Files Processed

| File | Errors Before | Errors After | Type |
|------|--------------|-------------|------|
| `JobDao.php` | 0 | 0 | Already clean (Phase 5C) |
| `MetadataDao.php` | 0 | 0 | Already clean (Phase 5C) |
| `JobsMetadataMarshaller.php` | 0 | 0 | Already clean |
| `MetadataStruct.php` | 0 | 0 | Already clean |
| `WarningsCountStruct.php` | 0 | 0 | Already clean |
| `LexiQaAndTagProjectionLanguages.php` | 2 | 0 | PHPDoc-only fixes |
| `JobStruct.php` | 14 | 0 | Mixed (TDD + type-only + cascade) |

#### LexiQaAndTagProjectionLanguages.php

| Fix | Error |
|-----|-------|
| `@var array<int, string>` | `$lexiQaAllowedLanguages` missing iterable value type |
| `@var array<string, string>` | `$tagProjectionAllowedLanguages` missing iterable value type |

Pure data class — no methods, no test file needed.

#### JobStruct.php — 14 Errors Fixed

**Type-only (10):**
- `@implements \ArrayAccess<string, mixed>` — class docblock
- `@return array{project_id: ?int, ...}` — getTMProps return type
- `: int` — getOpenThreadsCount return type
- Remove `?? []` dead code — getWarningsCount null coalesce
- `string $role` — getClientKeys parameter type
- `@return array<string, array<int, ClientTmKeyStruct>>` — getClientKeys return
- `@throws TypeError` — getClientKeys
- `int $_revisionNumber` — setSourcePage parameter type
- `@throws DomainException` — getSegments
- `@param array<int, mixed> $chunkReviews` + `@throws Exception` — getQualityOverall

**Type mismatches (2):**
- `project_id: int` → `project_id: ?int` — getTMProps
- `Model\TmKeyManagement\ClientTmKeyStruct` → `Utils\TmKeyManagement\ClientTmKeyStruct` — import path

**Behavioral (2, with TDD):**
- `setIsReview(?bool)` → `setIsReview(bool)` — narrow parameter type (callers pass only true)
- `getOutsource` loop: local-capture `$outsource` + `(array)` cast → property write-back after foreach mutation

**New test file:** `tests/unit/Structs/JobStructUnitTest.php` — 4 tests, 5 assertions

**Collateral cascading errors handled:**
- `AnalysisChunk.php` — +2 baseline entries (getMemoryKeys TypeError, trim argument.type)
- `Json/Job.php` — adjusted getKeyList TypeError count (1→2)

#### Verification

- **JobStruct alone without baseline**: 0 errors ✅
- **Full PHPStan**: 0 errors ✅
- **Full test suite**: 5,083 tests / 17,212 assertions, ALL PASSING ✅
- **Baseline**: 2,652 (net −14: −15 removed +3 cascading −2 warnings_count cascade fixes)
- **DI added to 9 uncovered methods**: `getTranslator(?JobsTranslatorsDao)`, `getOutsource(?ConfirmationDao)`, `getOpenThreadsCount(?CommentDao)`, `getWarningsCount(?WarningDao)`, `getChunks(?JobDao)`, `getClientKeys(?UserKeysModel)`, `getPeeForTranslatedSegments(?JobDao)`, `getSegments(?SegmentDao)`, `getErrorsCount(?WarningDao)` — pattern: `$dao ??= new XxxDao()` lazy fallback
- **New DI test file**: `tests/unit/Structs/JobStructDITest.php` — 30 tests, 33 assertions
  - Coverage after tests: **Methods 77.27%** (17/22, +6), **Lines 92.31%** (84/91, +56)
  - Remaining 5 uncovered: `getProject/getTMProps/getFiles/getQualityOverall/totalWordsCount` — all blocked by static calls (`ProjectDao::staticFindById`, `FileDao::getByJobId`, `CatUtils`, `WordCountStruct::loadFromJob`)
- **Per-file coverage**:
  - JobDao: 100% methods (30/30), 100% lines (267/267)
  - JobsMetadataMarshaller: 100% methods (1/1), 100% lines (7/7)
  - MetadataDao: 80% methods (8/10), 95.40% lines (83/87)
  - JobStruct: 50% methods (11/22), 32.10% lines (26/81)
  - LexiQaAndTagProjectionLanguages: pure data class (0 methods)
  - MetadataStruct / WarningsCountStruct: structs (0 methods)
- **Files added to ledger**: +5 (JobsMetadataMarshaller, JobStruct, LexiQaAndTagProjectionLanguages, MetadataStruct, WarningsCountStruct)

---

### Phase N+: Context-Review Wave — XliffConfigTemplate + Auth + ApiKeys + KeyCheck — ✅ DONE

**Date:** 2026-05-22

Multi-file sweep applying baseline reduction algorithm + >80% coverage across all 9 changed source files on the `context-review` branch.

#### Changes by file

**XliffConfigTemplateController.php** (−15 baseline entries):
- Refactored all 5 action methods to use instance DAO methods via DI
- Added `@throws TypeError` on all action methods, typed `validateJSON()` parameter
- Null guards on `$this->user->uid` with `TypeError`
- `file_get_contents()` false guard for schema validation

**XliffConfigTemplateDao.php** (0 baseline — already clean):
- Waves 1-4 refactor: migrated 10 public static methods to instance methods
- Lazy-initialized property + getter pattern (`??=` caching)
- `@deprecated` annotations on all static methods; new constructor accepting PDO

**AuthenticationHelper.php** (−1 baseline entry):
- Fixed `$user` property assignment: added null guard on `$userDao->getByUid()` result

**ApiKeyStruct.php** (−2 baseline entries):
- Added `@throws Exception` on `getUser()`, typed `validSecret(string $secret)`, DI for UserDao

**ApiKeyDao.php** (0 baseline — already clean):
- **Bug fix**: `findByKey()` used `$stmt->fetch() ?? null` but `PDO::fetch()` returns `false` not `null` — changed `??` to `?:`
- Added `@throws PDOException` on `findByKey()` and `create()`

**KeyCheckController.php** (0 baseline — already clean):
- Added DI for ApiKeyDao (`$this->getApiKeyDao()` with lazy init)

**NewController.php** (−2 baseline entries):
- Added `@throws DomainException|TypeError` on `sanitizeTmKeyArr()`

**CreateProjectController.php** (−2 baseline entries):
- Added `@throws DomainException|TypeError` on `sanitizeTmKeyArr()`

**ProjectTemplateDao.php** (0 baseline — already clean):
- Minor type fix

#### New/Extended Test Files

| File | Tests | Status |
|------|-------|--------|
| `KeyCheckControllerTest.php` (NEW) | 11 | New |
| `AuthenticationHelperTest.php` | 18 (was 8) | Extended |
| `ApiKeyDaoTest.php` | +6 | Extended |
| `ApiKeyStructTest.php` (NEW) | 6 | New |
| `XliffConfigTemplateControllerTest.php` (NEW) | — | New |
| `XliffConfigTemplateDAO/` (NEW) | — | New |

#### Coverage

| File | Coverage |
|------|----------|
| ApiKeyDao.php | 100% ✅ |
| ApiKeyStruct.php | 100% ✅ |
| KeyCheckController.php | 100% ✅ |
| AuthenticationHelper.php | 80.26% ✅ |
| XliffConfigTemplateController.php | 90.35% ✅ |
| XliffConfigTemplateDao.php | 99.19% ✅ |
| ProjectTemplateDao.php | 85.44% ✅ |
| NewController.php | 63.81% (@throws only) |
| CreateProjectController.php | 12.12% (@throws only) |

#### Summary

- **Baseline**: 2,590 → 2,569 (−21 entries)
- **Tests**: 5,231 (was 5,166), **Assertions**: 18,034 (was 17,383)
- **PHPStan**: 0 errors (full codebase with baseline)
- **Files added to ledger**: +3 (XliffConfigTemplateController, ApiKeyStruct, AuthenticationHelper)

---

### Phase 39: EntryValidator — DI refactor + coverage 0%→92% — ✅ DONE (−13 baseline entries, +10 tests)

**Why:** `lib/Model/LQA/EntryValidator.php` had 13 PHPStan errors and 0% coverage. All 5 internal DAO instantiations replaced with constructor injection (optional params, `??= new XxxDao()` fallback). Null guards added for all `property.nonObject` errors.

#### Changes

| File | Type | Notes |
|------|------|-------|
| `EntryValidator.php` | DI refactor | 5 DAOs injected in constructor: `SegmentDao`, `JobDao`, `ProjectDao`, `ModelDao`, `CategoryDao`; `??= new` lazy fallback; `$jobs[0] ?? throw NotFoundException` for job/project null guards; `$this->qa_model`/`$this->category` guarded with `?? throw NotFoundException`; typed array PHPDocs for `$errors`, `getErrors()`, `getErrorMessages()` |
| `EntryValidatorTest.php` | New test file | 10 tests covering: `isValid()` true/false paths, `ensureValid()` throw/no-throw, `validate()` throws for missing segment/job/project, error helper methods |

Key decisions:
- **All 5 DAOs → constructor** (not method-level): all are used in every validation pass, no scenario where one is needed without the others
- **`SegmentDao(Database::obtain())` stays in default path** — inject the DAO itself, isolate the static call to the fallback only
- **`EntryStruct` call site unchanged** — `new EntryValidator($this)` still works via lazy defaults
- **10 new tests**, 0 regressions

**Coverage:** 0% → **92.31%** lines, **80.00%** methods

---

### Phase 40: ConnectedServices OAuth Directory — Full Cleanup + Tests — ✅ DONE (−9 net baseline entries, +72 tests)

**Date:** 2026-05-28

**Why:** Complete `lib/Model/ConnectedServices/Oauth/` — all OAuth provider implementations, encryption, client singleton, plus controller and GDrive controller fixes. User refactored all provider files; this phase adds tests, fixes ConnectedServicesController (−3 baseline entries), GDriveController (−11 baseline entries), FileInsertionService (−3 baseline entries), and cleans up stale baseline entries.

#### Source Files Fixed

| File | Errors Fixed | Type |
|------|-------------|------|
| `ConnectedServicesController.php` | 3→0 | Null guard on `$this->connectedServiceStruct`, `@throws TypeError` |
| `GDriveController.php` | 16→0 | `@throws TypeError`, uid null guards, `json_encode` false guard, `filter_var` `string\|false` handling, typed `$error` array, typed `formatErrorMessage` param, `@param list<string>` on `doImport` |
| `FileInsertionService.php` | 3→0 | Logger context `array<string, mixed>` (was `array<int, mixed>`), `@throws TypeError` on `insertFiles` |

#### New Test Files (14 files, 72 tests)

| File | Tests | Notes |
|------|-------|-------|
| `ProviderUserTest.php` | 2 | Data class defaults + property setting |
| `AbstractProviderTest.php` | 2 | Concrete subclass, PROVIDER_NAME |
| `AccessTokenTest.php` | 2 | Constructor, `__toArray()` |
| `GoogleClientLogsFormatterTest.php` | 2 | `format()` JSON+newline, `formatBatch()` |
| `GoogleProviderTest.php` | 8 | Client config, auth URL, `getAccessTokenFromAuthCode` via testable subclass |
| `FacebookProviderTest.php` | 7 | Client, auth URL, `getAccessTokenFromAuthCode`, `getResourceOwner` with stub |
| `GithubProviderTest.php` | 8 | Client, auth URL, `getResourceOwner` name parsing, missing name TypeError |
| `LinkedInProviderTest.php` | 4 | Client (LinkedinFinal), auth URL; final class blocks deeper mocking |
| `LinkedinFinalTest.php` | 1 | `getResourceOwnerDetailsUrl()` returns v2/userinfo |
| `MicrosoftProviderTest.php` | 5 | Client, auth URL, missing config throws; final SDK classes block deeper mocking |
| `DefuseEncryptionTest.php` | 10 | Key create/load, encrypt/decrypt round-trip, null key TypeError |
| `OauthClientTest.php` | 12 | Singleton via reflection reset, all 5 providers, caching, auth URL, XSRF token |
| `ConnectedServiceStructTest.php` | +5 (extended) | `setEncryptedAccessToken`, decrypt round-trip, field extraction |
| `SessionTest.php` | +7 (extended) | `importFile` happy/error paths, `reConvert` success/error |

#### Coverage Summary (ConnectedServices Oauth)

| File | Coverage | Notes |
|------|----------|-------|
| `ProviderInterface.php` | n/a | Interface |
| `ProviderUser.php` | 100% | Data class |
| `DefuseEncryption.php` | ~90% | Key management + encrypt/decrypt |
| `OauthClient.php` | ~85% | Singleton, provider factory |
| `Google/AccessToken.php` | 100% | Value object |
| `Google/GoogleClientLogsFormatter.php` | 100% | PSR-3 formatter |
| `Google/GoogleProvider.php` | ~90% | Via testable subclass |
| `Facebook/FacebookProvider.php` | ~85% | Via testable subclass + stubs |
| `Github/GithubProvider.php` | ~90% | Via testable subclass + stubs |
| `LinkedIn/LinkedInProvider.php` | ~60% | Final SDK class blocks mocking |
| `LinkedIn/LinkedinFinal.php` | ~50% | Final class, limited testability |
| `Microsoft/MicrosoftProvider.php` | ~55% | Final SDK classes block mocking |
| `ConnectedServiceStruct.php` | ~80% | Encryption round-trip tests |
| `GDrive/Session.php` | 75% (was 64%) | importFile + reConvert via `getService()` override |

#### Baseline Changes

- **Removed:** 18 entries (11 GDriveController + 3 FileInsertionService + 3 ConnectedServicesController + 1 stale ReplaceEventIndexDAOInterface)
- **Added:** 5 entries (pre-existing errors on ledger files: EntryCommentDao, SegmentNoteDao, GlossaryWorker, ReplaceEventIndexDaoInterface — surfaced by PHPStan version/baseline cleanup)
- **Net:** 2,123 → **2,114** (−9)
- **Files added to ledger:** +14 (11 OAuth + ConnectedServicesController + GDriveController + FileInsertionService)
- **Ledger total:** 454 → **468**
- **Tests:** 5,987 tests, 16,338 assertions, 0 errors

---

### Phase 41: Search Directory — Full Cleanup + Tests — ✅ DONE (−9 baseline entries, +18 tests)

**Date:** 2026-05-28

**Why:** Complete `lib/Model/Search/` and `lib/Utils/Search/` — all 12 files PHPStan-clean, tests for ReplaceHistory, ReplaceHistoryFactory, and structs.

#### Files Fixed

| File | Errors Fixed | Type |
|------|-------------|------|
| `ReplaceEventIndexDaoInterface.php` | 1→0 | `@return mixed` → `@return int` |
| `ReplaceHistory.php` | 3→0 | Native param types `int $versionToMove`, `@throws PDOException` on `_moveToVersion`/`redo`/`undo` |
| `ReplaceHistoryFactory.php` | 6→0 | Native param types `int $id_job, string $driver, int $ttl`, `@throws Exception`, `@throws InvalidArgumentException` |
| `GetSearchController.php` (cascade) | 2→0 | `@throws Exception` on `getReplaceHistory`, `(int)` cast on `$replace_version` for `updateIndex` |

#### New Test Files (3 files, 18 tests)

| File | Tests | Notes |
|------|-------|-------|
| `ReplaceHistoryTest.php` | 8 | Mock-based via DAO interfaces: constructor TTL, get, getCursor, save, redo/undo (no events), updateIndex |
| `ReplaceHistoryFactoryTest.php` | 4 | redis/mysql drivers, invalid driver throws, zero TTL |
| `SearchStructsTest.php` | 6 | ReplaceEventStruct, ReplaceEventCurrentVersionStruct, SearchQueryParamsStruct defaults + properties |

#### DI Refactor (4 DAO files)

Injected Redis client and PDO connections as optional nullable constructor params — 0 caller changes.

| File | DI Params | Notes |
|------|-----------|-------|
| `RedisReplaceEventDao` | `?ClientInterface $redis`, `?SegmentTranslationDao` | Bypasses `RedisHandler` connection |
| `RedisReplaceEventIndexDao` | `?ClientInterface $redis` | Changed `Client` → `ClientInterface` |
| `MySQLReplaceEventDao` | `?PDO $pdo`, `?SegmentTranslationDao` | Bypasses `Database::obtain()` |
| `MySQLReplaceEventIndexDao` | `?PDO $pdo` | Bypasses `Database::obtain()` |

#### Coverage

| File | Before | After | Notes |
|------|--------|-------|-------|
| MySQLReplaceEventDao | 3.23% | **100%** | Mock PDO + PDOStatement |
| MySQLReplaceEventIndexDao | 5.56% | **100%** | Mock PDO, insert + update paths |
| RedisReplaceEventDao | 38.89% | **100%** | Mock ClientInterface |
| RedisReplaceEventIndexDao | 66.67% | **100%** | Mock ClientInterface |
| ReplaceHistoryFactory | 58.82% | **100%** | All drivers + invalid driver |
| ReplaceHistory | 75% | **85%** | redo/undo with events needs DB |
| SearchModel | 96.15% | **95.38%** | Pre-existing |

#### Baseline

- **Removed:** 9 entries (1 ReplaceEventIndexDaoInterface + 3 ReplaceHistory + 5 ReplaceHistoryFactory)
- **Net:** 2,114 → **2,105** (−9)
- **Files added to ledger:** +7 (2 interfaces + 3 structs + ReplaceHistory + ReplaceHistoryFactory)
- **Ledger total:** 468 → **475**
- **Tests:** 6,025 tests, 16,409 assertions, 0 errors

---

### Phase 38: OAuthSignInModel — DI refactor + coverage 0%→84% — ✅ DONE (+9 tests)

**Why:** File added to ledger in Phase 37 with 0% coverage. Per "no technical debt" rule, full DI refactor to enable testability.

#### Changes

| File | Type | Notes |
|------|------|-------|
| `OAuthSignInModel.php` | DI refactor | `array &$session` as first required param (live reference); `UserDao`, `MetadataDao`, `TeamDao` constructor-injected with `??= new` fallback; `_authenticateUser(?AuthenticationHelper)` optional param; `createWelcomeEmail()` + `createRedeemableProject()` protected factories |
| `OauthResponseHandlerController.php` | Caller update | Pass `$_SESSION` explicitly as first arg |

Key decisions:
- **Session as first required param** — eliminates nullable reference ambiguity; `=& $session` maintains live reference to caller's `$_SESSION`
- **`$_SESSION ??= []` guard** — initializes `$_SESSION` if not yet started (CLI/test context)
- **`UserDao`/`MetadataDao`/`TeamDao`** in constructor (used across multiple methods) — method-level injection only for `AuthenticationHelper` (runtime session dependency)
- **9 new tests** in `OAuthSignInModelTest.php` (16 assertions, 0 warnings)

**Coverage:** 0% → **83.67%** lines, **64.29%** methods

---

### Phase 37: LoginController + SignupController + UserController (V2) + OAuthSignInModel — ✅ DONE (−20 baseline entries)

**Why:** 4 files modified as call-site updates for AuthenticationHelper de-staticification. Per "no technical debt" rule, all touched files must be clean.

#### Changes

| File | Errors Fixed | Type |
|------|-------------|------|
| `LoginController.php` | 6→0 | `@throws` + `is_string()` guard on `getByEmail()` email param |
| `SignupController.php` | 8→0 | `@throws`, `@return array<string, mixed>`, `parse_url` false guard, `is_string()` password guards |
| `UserController.php` (V2) | 8→0 | `@throws`, `json_decode ?? ''`, `is_string()` type guards, `uid ?? throw` |
| `OAuthSignInModel.php` | 3→0 | `@throws TypeError` + `$email !== null` guard on `getByEmail()` |

Key behavioral fixes:
- `LoginController::login()`: `$dao->getByEmail(string|false|null)` → `is_string($email) ? $dao->getByEmail($email) : null`
- `SignupController`: `parse_url(string|false)` → explicit false check + safe host comparison
- `SignupController`: `validatePasswordRequirements(string|false|null)` → `is_string()` narrowing
- `UserController::setMetadata()`: `json_decode(string|null)` → `?? ''`, `foreach(null)` → `(array)$json`, uid null guard, `is_string()` key/value guards
- `OAuthSignInModel::signIn()`: `getByEmail(?string)` → null guard

**Cascade:** `OauthResponseHandlerController::_processSuccessfulOAuth()` gained `TypeError` cascade → added to baseline (not on ledger)

**Baseline reduction:** 2,232 → 2,212 (−20)
**Files added to ledger:** 4

---

### Phase 36: AuthenticationHelper de-staticification + coverage — ✅ DONE (−1 baseline entry, +12 tests)

**Why:** `AuthenticationHelper` used a Singleton pattern with 3 static methods (`getInstance`, `destroyAuthentication`, `refreshSession`). Per project rule "no technical debt, best architectural solution always." De-staticification enables full testability of `AuthenticationTrait` (was 42.42%) and `AuthenticationHelper` (stays 80%+).

#### Changes

| File | Type | Notes |
|------|------|-------|
| `AuthenticationHelper.php` | Refactor | Constructor `protected`→`public`; removed `$instance` singleton; `destroyAuthentication()` + `refreshSession()` converted to instance methods using `$this->session` |
| `AuthenticationTrait.php` | Caller update | `getInstance()` → `new AuthenticationHelper(...)`, `destroyAuthentication()` → `(new AuthenticationHelper($_SESSION))->destroyAuthentication()` |
| `KleinController.php` | Caller update + fix | `refreshSession()` → instance call; fixed `_logWithTime()` missing `@throws \InvalidArgumentException` (removed 1 stale baseline entry) |
| `BaseKleinViewController.php` | Cascade fix | Added `@throws \InvalidArgumentException` to `render()` |
| `LoginController.php` | Caller update | `getInstance()` → `new AuthenticationHelper($_SESSION)` |
| `SignupController.php` | Caller update | Same |
| `UserController.php` | Caller update | `refreshSession()` → instance call (2 sites) |
| `OAuthSignInModel.php` | Caller update | `getInstance()` → `new AuthenticationHelper($_SESSION)` |

Key decisions:
- **Singleton removed entirely**: no `$instance` static property, no `getInstance()`. Callers use `new AuthenticationHelper(...)` directly.
- **`refreshSession()` as instance method**: resets `$this->user`, `$this->logged`, `$this->api_record` + clears session vars (semantically equivalent to the old static behavior)
- **TDD**: 3 tests written RED first (instance methods called with no args), GREEN after implementation
- **Cascade**: baseline entries for `Bootstrap` and `OauthResponseHandlerController` updated from `Psr\Log\InvalidArgumentException` → `InvalidArgumentException` (same cascade, different exception class reported after `_logWithTime()` annotation fix)
- **12 new tests** in `AuthenticationHelperTest.php` (39 assertions)

**Coverage:** `AuthenticationHelper` 80.00% lines (maintained), `AuthenticationTrait` — measured via full suite

**Baseline reduction:** 2,233 → 2,232 (−1 KleinController entry)

---

### Phase 35: SearchModel — ✅ DONE (−12 net baseline entries, +3 tests)

**Why:** `SearchModel` is the core search/replace engine — handles source, target, coupled, and status-only search across segments. 15 PHPStan errors.

#### Changes

| File | Errors Fixed | Coverage Before → After | Notes |
|------|-------------|------------------------|-------|
| `lib/Model/Search/SearchModel.php` | 15→0 | 66.92% → 95.38% lines | PHPDoc types, null guards, redundant check removal |
| `lib/Controller/API/App/GetSearchController.php` | cascade | already clean | `@throws TypeError` added to 3 methods |

Key changes:
- **`@var Database` PHPDoc** was overriding native `IDatabase` type — removed (`assign.propertyType`)
- **`$searchTerm ?? ''`** — nullable source/target made `?? ''` explicit (3× `argument.type` errors)
- **Removed redundant `$occurrence[1] !== null`** — after `isset($occurrence[1])`, null already eliminated
- **Array shape PHPDocs**: `@return array{sid_list: list<string>, count: int}`, `array{string, array<string, mixed>}` for query-builders, `array<int, array<string, mixed>>` for `_getQuery`
- **`$vector['count'] = '0'`** → `0` (int) to match declared return type
- **`@throws TypeError`** added to constructor, `search()`, `_loadParams()`, and all 3 query-builders
- **Cascade**: `GetSearchController::search()`, `doSearch()`, `getSearchModel()` gained `@throws TypeError`
- **3 new tests** (coupled search, status_only search, default key) → 8 total in `SearchModelTest.php`

**Baseline reduction:** 2,245 → 2,233 (−12 net: −15 removed + 3 cascade in GetSearchController)

---

### Phase 34: FilesInfoUtility + FilesInfo — ✅ DONE (−8 net baseline entries, +10 tests)

**Why:** `FilesInfoUtility` is the core utility for the `/api/v3/files` endpoint — file metadata, instructions read/write. 22 PHPStan errors, 0 tests.

#### Changes

| File | Errors Fixed | Coverage Before → After | Notes |
|------|-------------|------------------------|-------|
| `lib/Model/Files/FilesInfoUtility.php` | 22→0 | 0% → ~85% | Constructor DI, null guard, `?? []` on nullable foreach |
| `lib/View/API/V3/Json/FilesInfo.php` | 2→0 | n/a | PHPDoc param/return type fixes |

Key changes:
- **Constructor DI**: 4 optional DAO params (`?JobDao`, `?MetadataDao`, `?FilesPartsDao`, `?FileDao`) with `?? new XxxDao()` fallbacks in constructor body — zero breaking change
- **Null guard**: `$projectId = $chunkStruct->getProject()->id` extracted as local variable, guarded with `RuntimeException` if null, stored as `private int $projectId` — resolves all 8 `argument.type` errors from `?int` passed as `int`
- **`foreach` null guard**: `getByJobIdProjectAndIdFile(...) ?? []` — fixes `foreach.nonIterable`
- **`FilesInfo::render()`**: `@param null` → `@param int|null` for params 2 & 3; `@return array<string, mixed>`
- **`@throws` + return types**: `getInfo()` → `array<string, mixed>`, `getInstructions()` → `array{instructions: mixed}|null`
- **Cascade**: 9 new entries added to baseline for `FileInfoController.php` (not on ledger)
- **13 new tests** in `FilesInfoUtilityTest.php` (30 assertions, 0 warnings) — **100% lines, 100% methods**

**Baseline reduction:** 2,253 → 2,245 (−8 net: −24 removed + 9 cascade added + 7 pre-existing stale)

---

### Phase 42: Xliff Directory — Full Cleanup + Tests — ✅ DONE (−10 baseline entries, +13 tests)

**Date:** 2026-05-29

**Why:** Complete `lib/Model/Xliff/` — all 8 files PHPStan-clean. 5 files already on ledger, 3 files fixed and added.

#### Files Fixed

| File | Errors Fixed | Type |
|------|-------------|------|
| `DefaultRule.php` | 5→0 | `@throws LogicException` on `setAnalysis()`, `@throws Exception` on `isTranslated()` |
| `XliffRulesModel.php` | 6→0 | `@var array<string, list<XliffRuleInterface>>` on `$ruleSets`, `new static()` → `new self()`, `@throws DomainException` on `getRulesForVersion()`, typed `jsonSerialize`/`getArrayCopy` returns, `json_encode` false guard in `__toString` |
| `XliffConfigTemplateStruct.php` | 2→0 | Typed `$rules` param on `hydrateRulesFromDataArray()`, typed `jsonSerialize()` return |

#### New Test Files (2 files, 13 tests)

| File | Tests | Notes |
|------|-------|-------|
| `DefaultRuleCoverageTest.php` | 5 | `asEditorStatus` branch coverage (signed-off, final, new states), `isTranslated` edge cases |
| `XliffConfigTemplateStructTest.php` | 8 | `hydrateFromJSON` (minimal, all fields, uid fallback, rules as array, rules as JSON string, error paths), `jsonSerialize` |

#### Coverage

| File | Methods | Lines |
|------|---------|-------|
| DefaultRule | 100% (3/3) | 100% (24/24) |
| XliffRulesModel | 62.5% (5/8) | 92.11% (35/38) |
| XliffConfigTemplateStruct | 75% (3/4) | 94.74% (36/38) |

#### Baseline

- **Removed:** 10 entries (2 DefaultRule + 6 XliffRulesModel + 2 XliffConfigTemplateStruct)
- **Net:** 2,059 → **2,049** (−10)
- **Files added to ledger:** +3 (DefaultRule, XliffRulesModel, XliffConfigTemplateStruct)
- **Ledger total:** 475 → **478**
- **Tests:** 6,175 tests, 16,746 assertions, 0 errors

---

### Phase 43: Users Directory — Full Cleanup + DI + Tests — ✅ DONE (−17 net baseline entries, +49 tests)

**Date:** 2026-05-29

**Why:** Complete `lib/Model/Users/` — all 11 files PHPStan-clean. 6 files already on ledger, 5 files fixed and added. DI refactor on ChangePasswordModel, PasswordResetModel, and UserStruct for testability.

#### Files Fixed

| File | Errors Fixed | Type |
|------|-------------|------|
| `ChangePasswordModel.php` | 6→0 | Native param types, null guards on salt/pass, `@throws TypeError`, DI for UserDao |
| `PasswordResetModel.php` | 6→0 | `@var`/`@param` array types, `@throws TypeError`, strtotime null guard, salt null guard, DI for UserDao |
| `ClientUserFacade.php` | 2→0 | `foreach` on non-iterable → `get_object_vars()`, `json_encode` false guard |
| `MetadataStruct.php` | 2→0 | `@var array<string, mixed>` on `$value`, `@return array<string, mixed>` on `jsonSerialize` |
| `UserStruct.php` | 6→0 | Native param types on `belongsToTeam`/`passwordMatch`, null guards on salt/pass, `@throws TypeError`/`RuntimeException`, `@return array<string, mixed>`, DI on 4 methods |

#### Cascade (algorithm step 6 — off-ledger → added to baseline)

- `ForgotPasswordController`: +3 entries (`@throws TypeError` cascade)
- `App/Authentication/UserController`: +3 entries (argument.type + `@throws TypeError`)
- `UserKeysController`: regex updated for `MetadataStruct::$value` type change

#### On-Ledger Cascade Fixed

- `LoginController`: `is_string($params['password'])` guard added (passwordMatch now expects `string`)

#### New Test Files (7 files, 49 tests)

| File | Tests | Notes |
|------|-------|-------|
| `ChangePasswordModelTest.php` | 5 | Success, wrong password, same password, email_confirmed, salt null |
| `PasswordResetModelTest.php` | 11 | Constructor, validateUser (3 paths), resetPassword (3 paths), flushWantedUrl (2), getUser |
| `ClientUserFacadeTest.php` | 2 | Constructor copies properties, toString returns JSON |
| `MetadataStructTest.php` | 8 | getValue (int, float, string, array, serialised, short string), jsonSerialize |
| `UserStructTest.php` | 14 | isLogged, fullName, shortName, getters, clearAuth, initAuth, passwordMatch null guards, everSignedIn, getDecrypted null |
| `UserStructDITest.php` | 9 | getPersonalTeam, getUserTeams, belongsToTeam (3 paths), getMetadataAsKeyValue (3 paths) |

#### Coverage

| File | Methods | Lines |
|------|---------|-------|
| ChangePasswordModel | 100% (2/2) | 100% (18/18) |
| PasswordResetModel | 100% (6/6) | 100% (40/40) |
| ClientUserFacade | 100% (2/2) | 100% (4/4) |
| MetadataStruct | 100% (3/3) | 100% (23/23) |
| UserStruct | 89.47% (17/19) | 83.61% (51/61) |

#### Baseline

- **Removed:** 22 entries (6 ChangePasswordModel + 6 PasswordResetModel + 2 ClientUserFacade + 2 MetadataStruct + 6 UserStruct)
- **Added:** 6 entries (3 ForgotPasswordController + 3 App/Authentication/UserController)
- **Updated:** 1 entry (UserKeysController regex for MetadataStruct type change)
- **Net:** 2,049 → **2,032** (−17)
- **Files added to ledger:** +5 (ChangePasswordModel, PasswordResetModel, ClientUserFacade, MetadataStruct, UserStruct)
- **Ledger total:** 478 → **483**
- **Tests:** 6,224 tests, 16,833 assertions, 0 errors

---

### Phase 44: Teams Directory — Full Cleanup + DI Refactor + Tests — ✅ DONE (−7 net baseline entries, +20 tests)

**Date:** 2026-05-29

**Why:** Complete `lib/Model/Teams/` — all 7 files PHPStan-clean. 4 files already on ledger, 3 files fixed and added.

#### Files Fixed

| File | Errors Fixed | Type |
|------|-------------|------|
| `InvitedUser.php` | 6→0 | `@var array<string, mixed>` on `$jwt`, `@throws TypeError/UnexpectedValueException/Exception`, typed `$invitation` param, null guard on `fetchById` result, **DI refactor:** static→instance methods, constructor-injected `TeamDao`+`RedisHandler`, caller updated in SignupController |
| `PendingInvitations.php` | 3→0 | `@var` typed property, native `int` on `hasPendingInvitation`, `@return array<string>`, `Client` → `ClientInterface`, `sadd` array param fix |
| `TeamStruct.php` | 1→0 | `getMembers()` return `?array` → `array` (property always initialized) |

#### Cascade Fixes

- **On-ledger:** TeamModel `getMembers() ?? []` → `getMembers()`, CattoolController same
- **Off-ledger → baseline:** TeamsInvitationsController (+1), TeamMembersController (+5), Team.php (+1), updated existing Membership `|null` entry

#### New Test Files (3 files, 11 tests)

| File | Tests | Notes |
|------|-------|-------|
| `TeamStructTest.php` | 5 | setMembers, getMembers, hasUser (3 paths) |
| `PendingInvitationsTest.php` | 4 | set, remove, hasPendingInvitation (2 paths). Anonymous ClientInterface impl |
| `InvitedUserTest.php` | 11 | Constructor (valid, empty, tampered, malformed), prepareSignUpRedirect, hasPendingInvitations (4 paths), completeTeamSignUp (success + not found) |

#### Coverage

| File | Methods | Lines | Notes |
|------|---------|-------|-------|
| TeamStruct | 100% (3/3) | 100% (7/7) | |
| PendingInvitations | 100% (4/4) | 100% (6/6) | |
| InvitedUser | 100% (4/4) | 100% (28/28) | DI refactor: static→instance, RedisHandler+TeamDao injected |

#### Baseline

- **Removed:** 10 entries (6 InvitedUser + 3 PendingInvitations + 1 TeamStruct)
- **Added:** 7 cascade (1 TeamsInvitationsController + 5 TeamMembersController + 1 Team.php)
- **Removed:** 1 stale TeamMembersController entry (Membership `|null` — getMembers no longer nullable)
- **Updated:** 1 UserKeysController regex
- **Net:** 2,032 → **2,025** (−7)
- **Files added to ledger:** +3 (InvitedUser, PendingInvitations, TeamStruct)
- **Ledger total:** 483 → **486**
- **Tests:** 6,244 tests, 16,858 assertions, 0 errors

---

### Phase 45: Constants Directory — Full Cleanup — ✅ DONE (−24 baseline entries, 0 tests needed)

**Date:** 2026-05-29

**Why:** Complete `lib/Utils/Constants/` — all 13 files PHPStan-clean. Pure constant/enum classes with no complex logic. 4 files already on ledger, 9 files fixed and added.

#### Files Fixed

| File | Errors Fixed | Type |
|------|-------------|------|
| `Constants.php` | 2→0 | `@var list<string>`, removed dead `== null` check (always false — `== ''` already catches null) |
| `Ices.php` | 1→0 | `@var list<string>` |
| `JobStatus.php` | 1→0 | `@var list<string>` |
| `Mime2Extension.php` | 2→0 | `@var array<string, list<string>>`, typed return |
| `ProjectStatus.php` | 2→0 | `@var list<string>`, native `string` param |
| `Teams.php` | 2→0 | `@var list<string>`, native `string` param |
| `TmKeyPermissions.php` | 1→0 | `@var list<string>` |
| `TranslationStatus.php` | 7→0 | 5× `@var` typed arrays, 2× native `string` params |
| `XliffTranslationStatus.php` | 6→0 | 6× native `?string` params (nullable — callers pass null) |

No cascade errors. No tests needed — pure data classes covered by caller tests.

#### Baseline

- **Removed:** 24 entries
- **Net:** 2,025 → **2,001** (−24)
- **Files added to ledger:** +9
- **Ledger total:** 486 → **495**
- **Tests:** 6,244 (unchanged)

---

### Phase 46: ProjectCreation Directory — Full Cleanup + Tests — ✅ DONE (−17 baseline entries, +3 tests)

**Date:** 2026-05-29

**Why:** Complete `lib/Model/ProjectCreation/` — all 13 files PHPStan-clean. 4 already on ledger, 9 fixed/added.

#### Files Fixed

| File | Errors Fixed | Type |
|------|-------------|------|
| `ProjectManagerModel.php` | 8→0 | `??` → `?:` on non-nullable `instance_id`, `@throws \Psr\Log\InvalidArgumentException` on 4 methods |
| `ProjectMetadataService.php` | 1→0 | Removed dead `isset()` on non-nullable `pretranslate_101` |
| `QAProcessor.php` | 1→0 | `@throws \DomainException` on `detectIcu` + cascade to `process` |
| `SegmentExtractor.php` | 6→0 | `XliffRulesModel::fromArray()` fix (was `new XliffRulesModel($arr)`), `@throws` cascades, `getXliffFileContent` private→protected for testability |
| `SegmentStorageService.php` | 5→0 | `(int)` cast on `$id_segment`, `@throws` on 4 methods |
| `TmKeyService.php` | 5→0 | `@throws \DomainException/\TypeError/\Psr\Log\InvalidArgumentException/\Exception` |

#### Cascade fixes (on-ledger: ProjectManager.php)

- `setPrivateTmKeysOrFail()`: `@throws \DomainException/\TypeError/\Psr\Log\InvalidArgumentException`
- `extractSegments()`, `extractSegmentsFromFiles()`: `@throws \TypeError`
- `storeSegments()`: `@throws \TypeError`
- `insertContextsForFile()`: `@throws \Psr\Log\InvalidArgumentException`

#### New Test Files

| File | Tests | Notes |
|------|-------|-------|
| `SegmentExtractorErrorPathsTest.php` | 3 | Error paths: null project ID, unreadable file, invalid XLIFF |

#### Coverage

| File | Methods | Lines | Notes |
|------|---------|-------|-------|
| ProjectMetadataService | 100% (2/2) | 100% (39/39) | |
| QAProcessor | 100% (4/4) | 100% (37/37) | |
| SegmentStorageService | 69% (9/13) | 97.10% (134/138) | |
| SegmentExtractor | 45% (10/22) | 80.63% (283/351) | +3 error path tests |
| ProjectManagerModel | 62% (8/13) | 83.49% (177/212) | +4 tests for contextGroups + insertFile |
| TmKeyService | 25% (2/8) | 81.72% (76/93) | +3 tests: pushTMX success, loop complete, loop error |

#### Baseline

- **Removed:** 17 entries
- **Net:** 2,001 → **1,984** (−17)
- **Files added to ledger:** +9 (6 fixed + 3 already clean)
- **Ledger total:** 495 → **501** (4 were already on ledger)
- **Tests:** 6,247 tests, 16,864 assertions, 0 errors

---

### Phase 47: LQA Directory — Full Cleanup — ✅ DONE (−12 baseline entries, 0 tests needed)

**Date:** 2026-05-29

**Why:** Complete `lib/Model/LQA/` — all 19 files PHPStan-clean. 10 already on ledger, 9 fixed/added. Pure struct/interface files with no complex logic.

#### Files Fixed

| File | Errors Fixed | Type |
|------|-------------|------|
| `CategoryStruct.php` | 1→0 | `@return array<string, mixed>` |
| `ChunkReviewStruct.php` | 1→0 | Added `: ?array` return type on `getUndoData()` |
| `EntryCommentStruct.php` | 1→0 | Removed unused `$ttl` from closure |
| `QAModelInterface.php` | 1→0 | `@return array<string, mixed>` |
| `QAModelTemplateCategoryStruct.php` | 1→0 | `@return array<string, mixed>` on `jsonSerialize` |
| `QAModelTemplatePassfailStruct.php` | 1→0 | Same |
| `QAModelTemplatePassfailThresholdStruct.php` | 1→0 | Same |
| `QAModelTemplateSeverityStruct.php` | 1→0 | Same |
| `QAModelTemplateStruct.php` | 4→0 | Native `string` param on `hydrateFromJSON`, `isset` → `!== 0` on non-nullable `$id`, null guard on `$passfail` property, `@throws \RuntimeException` |

No cascade errors. No tests needed — pure data structs covered by existing tests.

#### Baseline

- **Removed:** 12 entries
- **Net:** 1,984 → **1,972** (−12)
- **Files added to ledger:** +10 (9 fixed + 1 already clean EntryWithCategoryStruct)
- **Ledger total:** 504 → **510** (note: adjusted for Pagination entries already counted)
- **Tests:** 6,254 (unchanged)

---

## Next Action

1. **Push & verify CI** — confirm latest commits pass GitHub Actions
2. Continue PHPStan baseline reduction from remaining targets (1,972 entries)

---

## Remaining Baseline Analysis

**Core baseline:** 2,286 entries
**Plugin baseline:** ~0 entries addressed (aligner plugin — 737 errors in 11 files, separate concern)  
**By error type:** PHPDoc-only=~1,350 (59%), Behavioral=~663 (29%), Other=~273 (11%)

### Phase 6 Candidates — Prioritized

#### TIER 1: Easy Wins (≥70% PHPDoc-only, 15+ errors — fastest ROI)

| File                                               | Errors | %doc | PHPDoc | Behavioral | Notes |
|----------------------------------------------------|--------|------|--------|------------|-------|
| ~~`TranslationEventDao.php` (ReviewExtended)~~     | ~~27~~ | ~~96%~~ | ~~26~~ | ~~0~~ | ✅ Done (Phase 12) |
| ~~`View/V3/Json/Chunk.php`~~                       | ~~20~~ | ~~95%~~ | ~~19~~ | ~~0~~ | ✅ Done (Phase 12, refactored DI, 88% coverage) |
| ~~`Model/Projects/ManageModel.php`~~               | 19 | 94% | 18 | 1 | @throws + iterables |
| ~~`Utils/Logger/MatecatLogger.php`~~               | ~~19~~ | ~~100%~~ | ~~19~~ | ~~0~~ | ✅ Done (Phase 12, 100% coverage) |
| ~~`View/V3/Json/QualitySummary.php`~~              | 19 | 78% | 15 | 4 | ✅ Done (Phase 16, DI refactored, 96.58% coverage) |
| ~~`Model/QualityReport/QualityReportModel.php`~~   | ~~24~~ | ~~70%~~ | ~~17~~ | ~~1~~ | ✅ Done (Phase 13, DI refactored, 82.61% methods) |
| ~~`Controller/V3/QualityReportControllerAPI.php`~~ | ~~21~~ | ~~71%~~ | ~~15~~ | ~~6~~ | ✅ Done (Phase 13, 80% methods) |
| ~~`Utils/AsyncTasks/Workers/GlossaryWorker.php`~~  | ~~18~~ | ~~72%~~ | ~~13~~ | ~~2~~ | ✅ Done (Phase 17) |
| ~~`Model/Conversion/Filters.php`~~                 | ~~19~~ | ~~73%~~ | ~~14~~ | ~~2~~ | ✅ Done (Phase 18) |
| ~~`Model/Projects/ProjectModel.php`~~              | 18 | 72% | 13 | 5 | @throws cascade |
| ~~`View/App/Json/Analysis/AnalysisFile.php`~~      | ~~10~~ | ~~100%~~ | ~~10~~ | ~~0~~ | ✅ Done (Phase 12, 100% coverage) |
| ~~`View/V2/Json/Membership.php`~~                  | ~~12~~ | ~~83%~~ | ~~10~~ | ~~0~~ | ✅ Done (Phase 12, 100% coverage) |
| ~~`Controller/V2/SplitJobController.php`~~         | 15 | 86% | 13 | 0 | ✅ Done (Phase 19) |

**Subtotal Tier 1:** ~261 entries, ~228 PHPDoc-only (no TDD needed)

#### TIER 2: High-Value Controllers

| File                                    | Errors | %doc | PHPDoc | Behavioral | Notes |
|-----------------------------------------|--------|------|--------|------------|-------|
| ~~`GetSegmentsController.php`~~         | ~~27~~ | ~~59%~~ | ~~16~~ | ~~8~~ | Core editor endpoint |
| ~~`ModernMTController.php`~~            | 26 | 34% | 9 | 15 | MT integration — heavy behavioral |
| ~~`CattoolController.php`~~                 | ~~25~~ | ~~60%~~ | ~~15~~ | ~~1~~ | ✅ Done (Phase 23, +decorators, 28 tests) |
| ~~`SegmentTranslationIssueController.php`~~ | ~~21~~ | ~~96%~~ | ~~21~~ | ~~0~~ | ✅ Done (22 tests, 55 assertions) |
| `DownloadQRController.php`              | 18 | 66% | 12 | 6 | QR downloads |
| ~~`GetWarningController.php`~~           | ~~17~~ | ~~23%~~ | ~~4~~ | ~~12~~ | ✅ Done (Phase 22) |

**Subtotal Tier 2:** ~134 entries

#### TIER 3: Infrastructure/Models (cascade potential)

| File                                | Errors | %doc | PHPDoc | Behavioral | Notes |
|-------------------------------------|--------|------|--------|------------|-------|
| `Model/Analysis/XTRFStatus.php`     | 34 | 44% | 15 | 19 | Highest count, mixed |
| `Utils/TaskRunner/TaskManager.php`  | 33 | 9% | 3 | 28 | Almost all behavioral — hardest |
| ~~`GDrive/Session.php`~~             | ~~29~~ | ~~68%~~ | ~~20~~ | ~~9~~ | ✅ Done (Phase 28-29) |
| `Utils/Tools/PostEditing.php`       | 27 | 29% | 8 | 19 | Heavy behavioral |
| `Model/Analysis/AbstractStatus.php` | 25 | 56% | 14 | 9 | Analysis base class |
| ~~`QualityReportSegmentModel.php`~~ | ~~25~~ | ~~68%~~ | ~~17~~ | ~~3~~ | ✅ Done (Phase 13, DI refactored, 80% methods) |
| ~~`Model/WordCount/.php`~~          | 23 | 21% | 5 | 18 | Heavy behavioral |
| ~~`Utils/TMS/TMSService.php`~~      | 23 | 52% | 12 | 11 | TM service |

**Subtotal Tier 3:** ~219 entries

#### TIER 4: View Layer (JSON serializers)

| File | Errors | %doc | Notes |
|------|--------|------|-------|
| `View/V3/Json/Chunk.php` | 20 | 95% | Already in Tier 1 |
| `View/V3/Json/QualitySummary.php` | 19 | 78% | Already in Tier 1 |
| `View/Commons/ZipContentObject.php` | 13 | 61% | |
| `View/V2/Json/Job.php` | 13 | 76% | |
| `View/V2/Json/Membership.php` | 12 | 83% | |
| `View/App/Json/Analysis/AnalysisChunk.php` | 11 | 54% | |
| `View/V2/Json/SegmentVersion.php` | 11 | 54% | |
| `View/App/Json/Analysis/AnalysisFile.php` | 10 | 100% | Pure PHPDoc |

**Subtotal Tier 4:** ~109 entries

### Recommended Strategy

1. ~~**Batch Tier 1 PHPDoc-only files** (MatecatLogger, Chunk, ManageModel, AnalysisFile, Membership, SplitJobController) — ~90 entries, zero TDD, fast~~ ✅ Partially done (Phase 12 — MatecatLogger, Chunk, AnalysisFile, Membership)
2. ~~**Quality Report stack** (QualityReportModel + QualityReportSegmentModel + QualityReportControllerAPI + QualitySummary) — ~89 entries, domain cluster~~ ✅ Done (Phase 13 — QualityReportModel, QualityReportSegmentModel, QualityReportControllerAPI; Phase 16 — QualitySummary)
3. ~~**GlossaryWorker** — familiar worker pattern from contribution stack~~ ✅ Done (Phase 17)
4. **GetSegmentsController** — high business value, moderate difficulty
5. **Remaining Tier 1** — ManageModel (19), ProjectModel (18)

---

## Phase 28 — BaseFeature + PluginsLoader + coverage

**Date:** 2026-05-20
**Subagents:** 2 (BaseFeature fix+coverage, PluginsLoader coverage)
**Commit:** pending

### BaseFeature (lib/Plugins/Features/BaseFeature.php)
- **13 PHPStan errors fixed**: 7 type-only PHPDoc + 6 behavioral (null/false guards for `realpath()`, `parse_ini_file()`, `getFileName()`, `scandir()`)
- **12 new tests** via concrete `TestFeature` subclass
- **Coverage**: 94.44% lines, 85.71% methods
- **Cascade fixes**: AbstractRevisionFeature + SecondPassReview `$dependencies` type alignment

### PluginsLoader (lib/Model/FeaturesBase/PluginsLoader.php)
- **14 new unit tests** with reflection-based singleton reset
- **Coverage**: 93.33% lines (isolated)
- File already PHPStan-clean from Phase 27

### Cascade
- `AbstractRevisionFeature.php` (ON ledger): `$dependencies` type changed from `list<string>` to `array<int, string>`
- `SecondPassReview.php` (NOT on ledger): added `@var array<int, string>` annotation
- `plugins/airbnb/lib/Features/Airbnb.php` (NOT on ledger): resolved by BaseFeature type alignment
- Baseline entries also added for `plugins/aligner/.../SendTMXEmail.php` (LogicException cascade)

### Baseline
- 2,605 → 2,590 (−15: −13 BaseFeature removed + various cascades)
- Files clean: 294 → 296 (+2)

**Date:** 2026-05-20
**Subagents:** 14 (1 per file)
**Commit:** pending

### Summary
- **42 PHPStan errors fixed across 14 files** (39 `missingType.iterableValue`, 2 `missingType.checkedException`, 1 `new.static`)
- All errors were type-only PHPDoc additions except PluginsLoader which needed `@throws` annotations + `@phpstan-ignore` for `new static()`
- **14 new files on ledger**: all 14 files now 0 errors without baseline
- **Baseline**: 2,642 → 2,605 (−37: −44 resolved +5 cascading +2 basic)
- **Zero tests needed** — all changes were PHPDoc-only type annotations

### Cascade
- `GetSegmentsController.php` (ON ledger): added `@var array<string, mixed>` type-narrowing for PrepareNotesForRenderingEvent call
- `plugins/translated/lib/Features/Translated.php` (NOT on ledger): 3 new `argument.type` entries added to baseline
- `lib/Model/FeaturesBase/BasicFeatureStruct.php` (NOT on ledger): 2 new `missingType.checkedException` entries added to baseline

---

## Phase 26 — SignupModel (lib/Model/Users/Authentication/SignupModel.php)

**Date:** 2026-05-20
**Commit:** 7bfc054020

### Summary
- **12 PHPStan errors fixed** (5 `missingType.iterableValue`, 3 `missingType.checkedException`, 3 `argument.type`, 1 extra `missingType.checkedException`)
- **3 behavioral changes**: null guards in `__userAlreadyExists()`, `confirm()`, and `resendConfirmationEmail()`
- **DI added**: `?UserDao $userDao = null` + `?TeamDao $teamDao = null` in constructor; `?UserDao $dao = null` in static `resendConfirmationEmail()`
- **27 new unit tests** (19 were added in this phase; 8 net-new vs previous run)
- **Coverage**: 87.50% methods (14/16), 88.24% lines (75/85)
- **Baseline**: 2,652 → 2,642 (−10: −11 removed SignupModel, +1 cascading SignupController::create)

### Cascade
- `SignupController::create()` gained `missingType.checkedException` (TypeError from SignupModel::processSignup)
- `SignupController::confirm()` count updated from 1 to 2 (TypeError from SignupModel::confirm)

