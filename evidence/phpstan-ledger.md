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
