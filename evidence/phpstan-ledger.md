# PHPStan Baseline Reduction — Fixed Files Ledger

Branch: `fix-database-obtain`
Coverage target: ≥90% per file

Each file listed here is fully clean (0 errors, no-baseline) and must remain so.
Any change that introduces a new PHPStan error in a ledger file MUST be fixed, not baselined.

## Ledger

| File | Cleaned | Coverage |
|------|---------|----------|
| `lib/Controller/API/App/Authentication/LaraAuthController.php` | 2026-06-30 | pre-existing |
| `lib/Controller/API/App/XliffToTargetConverterController.php` | 2026-06-30 | 100% (prepareUploadedXliff/createFilters seams) |
| `lib/Controller/Views/OutsourceTo/AbstractController.php` | 2026-06-30 | 91.04% (refactor: setTemplateVars folded into setView; createLogger/createShopCart seams) |
| `lib/Controller/API/Commons/Validators/ChunkPasswordValidator.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Validators/ProjectValidator.php` | 2026-06-30 | 96% |
| `lib/Controller/API/V2/ProjectCompletionStatus.php` | 2026-06-30 | 96% (cascade) |
| `lib/Controller/API/V2/SegmentTranslationIssueController.php` | 2026-06-30 | pre-existing |
| `lib/Controller/API/Commons/Exceptions/AuthenticationError.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Exceptions/AuthorizationError.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Exceptions/ConflictError.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Exceptions/ExternalServiceException.php` | 2026-06-30 | 100% (typed ctor params) |
| `lib/Controller/API/Commons/Exceptions/NotFoundException.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Exceptions/UnprocessableException.php` | 2026-06-30 | 100% (typed ctor params) |
| `lib/Controller/API/Commons/Exceptions/ValidationError.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Validators/Base.php` | 2026-06-30 | 100% (Closure::fromCallable, typed variadic) |
| `lib/Controller/API/Commons/Validators/EngineOwnershipValidator.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Validators/InternalUserValidator.php` | 2026-06-30 | 100% (null-email guard) |
| `lib/Controller/API/Commons/Validators/IsOwnerInternalUserValidator.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Validators/JSONRequestValidator.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Validators/JobPasswordValidator.php` | 2026-06-30 | 100% (@throws Exception) |
| `lib/Controller/API/Commons/Validators/LoginValidator.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Validators/ProjectAccessTokenValidator.php` | 2026-06-30 | 100% (typed filter output) |
| `lib/Controller/API/Commons/Validators/ProjectAccessValidator.php` | 2026-06-30 | 100% (isLoggedIn + id_team guard) |
| `lib/Controller/API/Commons/Validators/ProjectExistsInTeamValidator.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Validators/ProjectPasswordValidator.php` | 2026-06-30 | 100% (typed filter output) |
| `lib/Controller/API/Commons/Validators/SegmentTranslation.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Validators/SegmentTranslationIssueValidator.php` | 2026-06-30 | 93.5% (null guards + reorder; 2 defensive throws uncovered) |
| `lib/Controller/API/Commons/Validators/SegmentValidator.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Validators/TeamAccessValidator.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Validators/TeamProjectValidator.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Validators/WhitelistAccessValidator.php` | 2026-06-30 | 100% (string-cast IP) |
| `lib/Controller/API/Commons/ViewValidators/MandatoryKeysValidator.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/ViewValidators/ViewLoginRedirectValidator.php` | 2026-06-30 | 100% (redirectToSignin seam) |
| `lib/Controller/API/GDrive/GDriveController.php` | 2026-06-30 | 98.2% (RenderTerminatedException redirect seam; +7 tests) |
| `lib/Controller/API/GDrive/OAuthController.php` | 2026-06-30 | 94.1% (typed __handleError+log, base-Exception import, @throws fixes; 1 line needs live Google token exchange) |
| `lib/Controller/API/App/AIAssistantController.php` | 2026-06-30 | PHPStan-clean (json_decode null-body guard); coverage pending — Wave 3 |
| `lib/Controller/API/App/CreateRandUserController.php` | 2026-07-01 | 100% (Wave 1; getEngine() seam → stub MyMemory) |
| `lib/Controller/API/App/HeartBeat.php` | 2026-07-01 | 100% (Wave 1; temp heartbeat-file path) |
| `lib/Controller/API/App/MyMemoryEntryStatusController.php` | 2026-07-01 | 100% (Wave 1; getMMEngine() private→protected seam → stub MyMemory) |
| `lib/Controller/API/App/OutsourceToController.php` | 2026-06-30 | PHPStan-clean (@throws Exception/TypeError/InvalidArgumentException + array value type); coverage pending — Wave 2 |
| `lib/Controller/API/App/RequestExportTMXController.php` | 2026-07-01 | 100% (Wave 1; createTMSService() seam → avoid live HTTP) |
| `lib/Controller/API/App/FetchChangeRatesController.php` | 2026-07-01 | 100% (Wave 1; getChangeRatesFetcher() seam → stub fetcher) |
| `lib/Controller/API/App/LaraController.php` | 2026-07-01 | 100% (Wave 1) |
| `lib/Controller/API/App/QualityReportControllerAPI.php` | 2026-07-01 | 100% (Wave 1; delegates to V3 segments(true)) |
| `lib/Controller/API/App/SupportedLanguagesController.php` | 2026-07-01 | 100% (Wave 1; Languages::getInstance) |
| `lib/Controller/API/App/TeamsInvitationsController.php` | 2026-07-01 | 100% (Wave 1; no DB seed on covered path) |
| `lib/Controller/API/App/AjaxUtilsController.php` | 2026-07-01 | 100% (Wave 1; engine id=1 class_load swap+restore for checkTMKey) |
| `lib/Controller/API/App/ContextUrlSchemaController.php` | 2026-07-01 | 100% (Wave 1; RuntimeException branch via AppConfig::$ROOT swap) |
| `lib/Controller/API/App/GetVolumeAnalysisController.php` | 2026-07-01 | 100% (Wave 1; real project+job seed block 9_972_000 drives analysis() fallback) |
| `lib/Controller/API/V3/CancelRequestController.php` | 2026-06-30 | pre-existing (≥90%) |
| `lib/Controller/API/V3/ChunkController.php` | 2026-06-30 | pre-existing (≥90%) |
| `lib/Controller/API/V3/CountWordController.php` | 2026-06-30 | 96.2% (filter method/limit casts; 1 dead defensive guard) |
| `lib/Controller/API/V3/DeepLGlossaryController.php` | 2026-06-30 | pre-existing (≥90%) |
| `lib/Controller/API/V3/DownloadQRController.php` | 2026-06-30 | pre-existing (≥90%) |
| `lib/Controller/API/V3/FileInfoController.php` | 2026-06-30 | 90.2% (pre-existing) |
| `lib/Controller/API/V3/FiltersConfigTemplateController.php` | 2026-06-30 | 93.6% (pre-existing) |
| `lib/Controller/API/V3/IssueCheckController.php` | 2026-06-30 | pre-existing (≥90%) |
| `lib/Controller/API/V3/LaraController.php` | 2026-06-30 | 100% (new test) |
| `lib/Controller/API/V3/MetaDataController.php` | 2026-06-30 | pre-existing (≥90%) |
| `lib/Controller/API/V3/ModernMTController.php` | 2026-06-30 | 90.9% (CSV null-cell annotations; HTTP-kernel/dead-code lines uncovered) |
| `lib/Controller/API/V3/MyMemoryController.php` | 2026-06-30 | 95.8% (2 valid-key lines need live service) |
| `lib/Controller/API/V3/PayableRateController.php` | 2026-06-30 | 100% |
| `lib/Controller/API/V3/ProjectTemplateController.php` | 2026-06-30 | 100% |
| `lib/Controller/API/V3/QAModelTemplateController.php` | 2026-06-30 | 94.1% (new test; typed params, instanceof guard, pagination clamp, schema null-guard) |
| `lib/Controller/API/V3/QualityReportControllerAPI.php` | 2026-06-30 | pre-existing (≥90%) |
| `lib/Controller/API/V3/RevisionFeedbackController.php` | 2026-06-30 | pre-existing (≥90%) |
| `lib/Controller/API/V3/SegmentAnalysisController.php` | 2026-06-30 | 100% |
| `lib/Controller/API/V3/StatusController.php` | 2026-06-30 | 100% |
| `lib/Controller/API/V3/TeamsProjectsController.php` | 2026-06-30 | 100% |
| `lib/Controller/API/V3/TmKeyManagementController.php` | 2026-06-30 | pre-existing (≥90%) |
| `lib/Controller/API/V3/XliffConfigTemplateController.php` | 2026-06-30 | pre-existing (≥90%) |
