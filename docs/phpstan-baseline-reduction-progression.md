# PHPStan Baseline Reduction — Comprehensive Progression

**Branch:** `context-review` (based on `develop`)  
**Date:** 2026-05-07 (last updated)  
**Commits (refactor + fix + security + test):** 41  

| Metric | develop (baseline) | context-review (current) | Delta |
|--------|-------------------|--------------------------|-------|
| **PHPStan baseline entries** | 7,366 | 3,206 | −4,160 (−56.5%) |
| **PHPUnit tests** | ~2,248 | 3,697 | +1,449 (+64.5%) |
| **PHPUnit assertions** | ~19,449 | 23,490 | +4,041 (+20.8%) |
| **Coverage — Classes** | 8.48% (53/625) | 19.94% (135/677) | +11.46% (+82 classes) |
| **Coverage — Methods** | 21.74% (844/3,883) | 37.14% (1,517/4,085) | +15.40% (+673 methods) |
| **Coverage — Lines** | 21.19% (7,273/34,320) | 36.68% (12,737/34,727) | +15.49% (+5,464 lines) |
| **New test files** | 235 | 287+ | +52 |
| **Files fully clean (0 PHPStan errors)** | 0 | 54+ | — |

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
3. **Types MUST be certain** — no speculative type changes. Confirm actual runtime behavior via tests/callers before narrowing or changing a type.
4. **Minimize scope** — fix the PHPStan error, don't refactor surrounding code.
5. **No `@phpstan-ignore`** or baseline suppression.

### Baseline Reduction Algorithm (MANDATORY)

Every file we touch **MUST** be clean. The baseline is managed by surgical removal, never regeneration.

1. **Maintain a fixed-files ledger** — a persistent list of every file we've already cleaned (see below).
2. **Pick a new file** to clean from the baseline.
3. **Fix all PHPStan errors** in that file.
4. **Test the file alone with no baseline** (`cp phpstan-baseline.neon phpstan-baseline.neon.bak && echo "" > phpstan-baseline.neon && php vendor/bin/phpstan analyse <file> --no-progress --error-format=table; cp phpstan-baseline.neon.bak phpstan-baseline.neon`) — it **must** report zero errors.
5. **Run PHPStan on the full codebase with the baseline** — this surfaces only **new** errors (ones not already recorded in the baseline).
6. **For each new error found:**
   - If the error is in a file **on our fixed-files ledger** → **fix it** (that file must stay clean).
   - If the error is in a file **not on our ledger** → **add it to the baseline** (we haven't committed to cleaning it yet).
7. **Add the newly cleaned file** to the fixed-files ledger.
8. **Manually remove** all resolved entries for that file from `phpstan-baseline.neon`. **NEVER regenerate the baseline.** Regenerating resets the baseline to the current state, potentially re-whitelisting errors in files we've already committed to keeping clean.
9. **Repeat from step 2.**

### TDD Specifics

- **Behavioral changes** (null guards, new exceptions, restructured control flow) → strict TDD red/green. Write the failing test FIRST (red), then apply the minimal fix (green).
- **Type-only annotations** (`@throws`, `@return`, `@param` PHPDocs) → don't require red/green since PHPStan itself is the verifier.

### Coverage Target

- When fixing PHPStan errors in a file, the goal is also to **increase test coverage above 80%** for that file. Tests must cover the fixed code paths, not just satisfy PHPStan.

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

**Total: 166 files** (verified via `git diff --name-only 7d529165b7...HEAD` cross-referenced with `phpstan-baseline.neon`)

<!-- Baseline: commit 7d529165b726b3b721de43805133d02c3f8f5a1b ("fix PHPStan level-8 type errors and remove dead _buildResult overrides") -->
<!-- To verify: cp phpstan-baseline.neon phpstan-baseline.neon.bak && echo "" > phpstan-baseline.neon && php vendor/bin/phpstan analyse <file> --no-progress; cp phpstan-baseline.neon.bak phpstan-baseline.neon -->

<details>
<summary>Click to expand full ledger (166 files)</summary>

#### Controller Abstracts & Auth
| File | Cleaned In |
|------|-----------|
| `lib/Controller/Abstracts/AbstractDownloadController.php` | Phase 1B |
| `lib/Controller/Abstracts/Authentication/AuthCookie.php` | Phase 1E |
| `lib/Controller/Abstracts/Authentication/AuthenticationTrait.php` | Phase 1G |
| `lib/Controller/Abstracts/Authentication/CookieManager.php` | Phase 1F |
| `lib/Controller/Abstracts/Authentication/SessionTokenStoreHandler.php` | Phase 1D |
| `lib/Controller/Abstracts/FlashMessage.php` | Phase 0 |
| `lib/Controller/Exceptions/RenderTerminatedException.php` | Phase 13D |

#### Controller API
| File | Cleaned In |
|------|-----------|
| `lib/Controller/API/App/Authentication/LaraAuthController.php` | Phase 0 |
| `lib/Controller/API/App/CommentController.php` | Phase 5D |
| `lib/Controller/API/App/DeleteContributionController.php` | Phase 5C |
| `lib/Controller/API/App/DownloadAnalysisReportController.php` | Phase 1B |
| `lib/Controller/API/App/EngineController.php` | Phase 0 |
| `lib/Controller/API/App/GetContributionController.php` | Phase 5C |
| `lib/Controller/API/App/GetSearchController.php` | Phase 5E |
| `lib/Controller/API/App/QualityFrameworkController.php` | Phase 13C |
| `lib/Controller/API/App/SetTranslationController.php` | Phase 5 |
| `lib/Controller/API/V2/DownloadController.php` | Phase 14 |
| `lib/Controller/API/V2/ProjectCreationStatusController.php` | Phase 0 |
| `lib/Controller/API/V3/LaraController.php` | Phase 0 |
| `lib/Controller/API/V3/SegmentAnalysisController.php` | Phase 8A |

#### Controller Traits & Views
| File | Cleaned In |
|------|-----------|
| `lib/Controller/Traits/APISourcePageGuesserTrait.php` | Phase 0 |
| `lib/Controller/Traits/SegmentDisabledTrait.php` | Phase 8A |
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
| `lib/Model/DataAccess/RecursiveArrayCopy.php` | Phase 0 |
| `lib/Model/DataAccess/ShapelessConcreteStruct.php` | Phase 2B |
| `lib/Model/DataAccess/TransactionalTrait.php` | Phase 7B |
| `lib/Model/DataAccess/XFetchEnvelope.php` | Phase 2D |

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
| `lib/Model/Engines/Structs/MicrosoftHubStruct.php` | Phase 0 |
| `lib/Model/Engines/Structs/MMTStruct.php` | Phase 0 |
| `lib/Model/Engines/Structs/NONEStruct.php` | Phase 0 |
| `lib/Model/Engines/Structs/SmartMATEStruct.php` | Phase 0 |
| `lib/Model/Engines/Structs/YandexTranslateStruct.php` | Phase 0 |

#### Model/FeaturesBase (Events)
| File | Cleaned In |
|------|-----------|
| `lib/Model/FeaturesBase/Hook/Event/Filter/AnalysisBeforeMTGetContributionEvent.php` | Phase 5B |
| `lib/Model/FeaturesBase/Hook/Event/Filter/FilterRevisionChangeNotificationListEvent.php` | Phase 0 |
| `lib/Model/FeaturesBase/Hook/Event/Filter/RewriteContributionContextsEvent.php` | Phase 0 |

#### Model/Files & FilesStorage
| File | Cleaned In |
|------|-----------|
| `lib/Model/Files/FilesPartsStruct.php` | Phase 0 |
| `lib/Model/FilesStorage/AbstractFilesStorage.php` | Phase 6B |
| `lib/Model/FilesStorage/FsFilesStorage.php` | Phase 6B |
| `lib/Model/FilesStorage/IFilesStorage.php` | Phase 6B |

#### Model/Jobs & LQA
| File | Cleaned In |
|------|-----------|
| `lib/Model/Jobs/ChunkDao.php` | Phase 0 |
| `lib/Model/LQA/CategoryDao.php` | Phase 0 |
| `lib/Model/LQA/ModelDao.php` | Phase 0 |
| `lib/Model/LQA/ModelStruct.php` | Phase 0 |

#### Model/Projects
| File | Cleaned In |
|------|-----------|
| `lib/Model/Projects/ManageModel.php` | Phase 14 |
| `lib/Model/Projects/ProjectStruct.php` | Phase 14 |
| `lib/Model/Projects/ProjectTemplateStruct.php` | Phase 0 |

#### Model (other)
| File | Cleaned In |
|------|-----------|
| `lib/Model/ConnectedServices/ConnectedServiceStruct.php` | Phase 0 |
| `lib/Model/ConnectedServices/Oauth/OauthTokenEncryption.php` | Phase 0 |
| `lib/Model/Outsource/ConfirmationStruct.php` | Phase 0 |
| `lib/Model/Propagation/PropagationTotalStruct.php` | Phase 0 |
| `lib/Model/QualityReport/QualityReportDao.php` | Phase 13 |
| `lib/Model/QualityReport/QualityReportModel.php` | Phase 13B |
| `lib/Model/QualityReport/QualityReportSegmentModel.php` | Phase 13B |
| `lib/Model/QualityReport/QualityReportSegmentStruct.php` | Phase 13A |
| `lib/Model/ReviseFeedback/FeedbackDAO.php` | Phase 0 |
| `lib/Model/Search/MySQLReplaceEventDAO.php` | Phase 0 |
| `lib/Model/Search/MySQLReplaceEventIndexDAO.php` | Phase 0 |
| `lib/Model/Search/RedisReplaceEventIndexDAO.php` | Phase 0 |
| `lib/Model/Segments/SegmentOriginalDataDao.php` | Phase 0 |
| `lib/Model/Segments/SegmentUIStruct.php` | Phase 0 |
| `lib/Model/Teams/MembershipStruct.php` | Phase 0 |
| `lib/Model/Teams/TeamModel.php` | Phase 6A |
| `lib/Model/TmKeyManagement/MemoryKeyDao.php` | Phase 6C |
| `lib/Model/TmKeyManagement/MemoryKeyStruct.php` | Phase 6C |
| `lib/Model/TmKeyManagement/UserKeysModel.php` | Phase 6C |
| `lib/Model/Translators/JobsTranslatorsStruct.php` | Phase 0 |
| `lib/Model/Translators/TranslatorsModel.php` | Phase 6D |
| `lib/Model/Xliff/DTO/AbstractXliffRule.php` | Phase 0 |
| `lib/Model/Xliff/DTO/XliffRuleInterface.php` | Phase 0 |

#### Plugins
| File | Cleaned In |
|------|-----------|
| `lib/Plugins/Features/RevisionFactory.php` | Phase 13A |
| `lib/Plugins/Features/SegmentFilter/Model/SegmentFilterDao.php` | Phase 0 |
| `lib/Plugins/Features/TranslationEvents/Model/TranslationEventDao.php` | Phase 12A |
| `lib/Plugins/Features/TranslationVersions/Model/TranslationVersionDao.php` | Phase 0 |

#### Utils/Workers & Contribution
| File | Cleaned In |
|------|-----------|
| `lib/Utils/AsyncTasks/Workers/GetContributionWorker.php` | Phase 4A |
| `lib/Utils/AsyncTasks/Workers/SetContributionMTWorker.php` | Phase 5B |
| `lib/Utils/AsyncTasks/Workers/SetContributionWorker.php` | Phase 5B |
| `lib/Utils/AsyncTasks/Workers/Traits/MatchesComparator.php` | Phase 4C |
| `lib/Utils/AsyncTasks/Workers/Traits/ProjectWordCount.php` | Phase 4C |
| `lib/Utils/Constants/EngineConstants.php` | Phase 6C |
| `lib/Utils/Contribution/ContributionContexts.php` | Phase 4A |
| `lib/Utils/Contribution/GetContributionRequest.php` | Phase 4A |
| `lib/Utils/Contribution/SetContributionRequest.php` | Phase 5B |
| `lib/Utils/Date/DateTimeUtil.php` | Phase 0 |

#### Utils/Email
| File | Cleaned In |
|------|-----------|
| `lib/Utils/Email/MembershipCreatedEmail.php` | Phase 12A |
| `lib/Utils/Email/MembershipDeletedEmail.php` | Phase 12A |

#### Utils/Engines (full hierarchy)
| File | Cleaned In |
|------|-----------|
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
| `lib/Utils/Engines/MMT/MMTServiceApiException.php` | Phase 0 |
| `lib/Utils/Engines/MMT.php` | Phase 14 |
| `lib/Utils/Engines/NONE.php` | Phase 0 |
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
| `lib/Utils/LQA/QA/SymbolChecker.php` | Phase 9A |
| `lib/Utils/LQA/SizeRestriction/CJKLangUtils.php` | Phase 9A |
| `lib/Utils/LQA/SizeRestriction/EmojiUtils.php` | Phase 9A |

#### Utils (other)
| File | Cleaned In |
|------|-----------|
| `lib/Utils/Logger/MatecatLogger.php` | Phase 12A |
| `lib/Utils/Templating/PHPTALWithAppend.php` | Phase 0 |
| `lib/Utils/TmKeyManagement/Filter.php` | Phase 6C |
| `lib/Utils/TmKeyManagement/ShareKeyEmail.php` | Phase 6C |
| `lib/Utils/TmKeyManagement/TmKeyManager.php` | Phase 6C |
| `lib/Utils/TmKeyManagement/TmKeyStruct.php` | Phase 6C |
| `lib/Utils/Tools/CatUtils.php` | Phase 3A |
| `lib/Utils/Tools/SimpleJWT.php` | Phase 0 |
| `lib/Utils/Tools/Utils.php` | Phase 3B |
| `lib/Utils/Validator/IsJobRevisionValidator.php` | Phase 13A |

#### View
| File | Cleaned In |
|------|-----------|
| `lib/View/API/App/Json/Analysis/AnalysisFile.php` | Phase 12A |
| `lib/View/API/App/Json/Analysis/AnalysisFileMetadata.php` | Phase 12A |
| `lib/View/API/V2/Json/JobTranslator.php` | Phase 0 |
| `lib/View/API/V2/Json/Membership.php` | Phase 12A |

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

#### 4B. `FastAnalysis.php` — ✅ DONE (commit `a21971d0a2`, −35 entries + daemon fix)

35 baseline entries eliminated + 1 non-baselined daemon error fixed. Key changes:
- `requireQueueHandler()` helper — eliminates 12 `method.nonObject` errors from nullable `?AMQHandler`
- `instanceof MyMemory` narrowing — proper type-safe engine access for `fastAnalysis()`
- `instanceof Database` guard for `ping()` — `IDatabase` lacks the method
- Native param types on `_updateProject(int, string)`, `_fetchMyMemoryFast(int)`, `_getSegmentsForFastVolumeAnalysis(int)`, `_executeInsert(array, array)`, `_getWordCountForSegment(array, array)`
- Array shape PHPDocs for properties (`$segments`, `$segment_hashes`, `$actual_project_row`)
- `@throws PDOException` on `_checkDatabaseConnection()`
- `@throws RuntimeException` on `cleanShutDown()`
- `date_create()` → `new \DateTime()` (cannot return false)
- `is_null(int)` → `!== 0` for `AppConfig::$INSTANCE_ID`
- `(int)$id_job` cast for `MetadataDao::get()` calls
- Null guard for `$pid = $projectStruct->id` (nullable `?int`)
- `$queueInfo` null check before queue operations
- `rpush()` wraps value in array as Predis requires
- Fixed `AbstractEngine::syncMemories()` PHPDoc: `array<string, mixed>|null` → `list<array<string, mixed>>|null`
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
Driver: Xdebug 3.5.0, PHP 8.3.30, PHPUnit 12.5.23

| Metric | Value |
|--------|-------|
| **Total tests** | 3,697 |
| **Assertions** | 23,490 |
| **Warnings** | 0 |
| **Status** | ALL PASSING |

### Coverage Analysis

- **Class coverage more than doubled** (8.48% → 19.94%) — 82 additional classes now have test coverage, primarily structs, DAO files, controllers, and QR models that were previously untested.
- **Method coverage jumped +15.40%** (21.74% → 37.14%) — 673 additional methods covered, driven by new typed accessors, controller test harnesses, and QR model DI refactors.
- **Line coverage grew by +15.49%** (21.19% → 36.68%) — 5,464 additional lines covered while total lines grew by only 407.
- **Total classes grew by 52** (625 → 677) — new struct types, validators, exceptions, and test infrastructure added.
- **Total methods grew by 202** (3,883 → 4,085) — new typed accessors, factory methods, and protected DI wrappers.

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

## Next Action

1. **Push & verify CI** — confirm latest commits pass GitHub Actions
2. Continue PHPStan baseline reduction from candidates below

---

## Remaining Baseline Analysis

**Core baseline:** 2,527 entries in ~435 files  
**Plugin baseline:** ~733 entries (mostly aligner plugin — separate concern)  
**By error type:** PHPDoc-only=~1,500 (59%), Behavioral=~700 (27%), Other=~327 (12%)

### Phase 6 Candidates — Prioritized

#### TIER 1: Easy Wins (≥70% PHPDoc-only, 15+ errors — fastest ROI)

| File | Errors | %doc | PHPDoc | Behavioral | Notes |
|------|--------|------|--------|------------|-------|
| ~~`TranslationEventDao.php` (ReviewExtended)~~ | ~~27~~ | ~~96%~~ | ~~26~~ | ~~0~~ | ✅ Done (Phase 12) |
| ~~`View/V3/Json/Chunk.php`~~ | ~~20~~ | ~~95%~~ | ~~19~~ | ~~0~~ | ✅ Done (Phase 12, refactored DI, 88% coverage) |
| `Model/Projects/ManageModel.php` | 19 | 94% | 18 | 1 | @throws + iterables |
| ~~`Utils/Logger/MatecatLogger.php`~~ | ~~19~~ | ~~100%~~ | ~~19~~ | ~~0~~ | ✅ Done (Phase 12, 100% coverage) |
| `View/V3/Json/QualitySummary.php` | 19 | 78% | 15 | 4 | Mostly iterables |
| ~~`Model/QualityReport/QualityReportModel.php`~~ | ~~24~~ | ~~70%~~ | ~~17~~ | ~~1~~ | ✅ Done (Phase 13, DI refactored, 82.61% methods) |
| ~~`Controller/V3/QualityReportControllerAPI.php`~~ | ~~21~~ | ~~71%~~ | ~~15~~ | ~~6~~ | ✅ Done (Phase 13, 80% methods) |
| `Utils/AsyncTasks/Workers/GlossaryWorker.php` | 18 | 72% | 13 | 2 | Worker pattern (familiar) |
| `Model/Conversion/Filters.php` | 19 | 73% | 14 | 2 | Iterables-heavy |
| `Model/Projects/ProjectModel.php` | 18 | 72% | 13 | 5 | @throws cascade |
| ~~`View/App/Json/Analysis/AnalysisFile.php`~~ | ~~10~~ | ~~100%~~ | ~~10~~ | ~~0~~ | ✅ Done (Phase 12, 100% coverage) |
| ~~`View/V2/Json/Membership.php`~~ | ~~12~~ | ~~83%~~ | ~~10~~ | ~~0~~ | ✅ Done (Phase 12, 100% coverage) |
| `Controller/V2/SplitJobController.php` | 15 | 86% | 13 | 0 | @throws + iterables |

**Subtotal Tier 1:** ~261 entries, ~228 PHPDoc-only (no TDD needed)

#### TIER 2: High-Value Controllers

| File | Errors | %doc | PHPDoc | Behavioral | Notes |
|------|--------|------|--------|------------|-------|
| `GetSegmentsController.php` | 27 | 59% | 16 | 8 | Core editor endpoint |
| `ModernMTController.php` | 26 | 34% | 9 | 15 | MT integration — heavy behavioral |
| `CattoolController.php` | 25 | 60% | 15 | 1 | View controller |
| `SegmentTranslationIssueController.php` | 21 | 47% | 10 | 9 | LQA endpoint |
| `DownloadQRController.php` | 18 | 66% | 12 | 6 | QR downloads |
| `GetWarningController.php` | 17 | 23% | 4 | 12 | QA warnings — heavy behavioral |

**Subtotal Tier 2:** ~134 entries

#### TIER 3: Infrastructure/Models (cascade potential)

| File | Errors | %doc | PHPDoc | Behavioral | Notes |
|------|--------|------|--------|------------|-------|
| `Model/Analysis/XTRFStatus.php` | 34 | 44% | 15 | 19 | Highest count, mixed |
| `Utils/TaskRunner/TaskManager.php` | 33 | 9% | 3 | 28 | Almost all behavioral — hardest |
| `GDrive/Session.php` | 29 | 68% | 20 | 9 | GDrive integration |
| `Utils/Tools/PostEditing.php` | 27 | 29% | 8 | 19 | Heavy behavioral |
| `Model/Analysis/AbstractStatus.php` | 25 | 56% | 14 | 9 | Analysis base class |
| ~~`QualityReportSegmentModel.php`~~ | ~~25~~ | ~~68%~~ | ~~17~~ | ~~3~~ | ✅ Done (Phase 13, DI refactored, 80% methods) |
| `Model/WordCount/CounterModel.php` | 23 | 21% | 5 | 18 | Heavy behavioral |
| `Utils/TMS/TMSService.php` | 23 | 52% | 12 | 11 | TM service |

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
2. ~~**Quality Report stack** (QualityReportModel + QualityReportSegmentModel + QualityReportControllerAPI + QualitySummary) — ~89 entries, domain cluster~~ ✅ Done (Phase 13 — QualityReportModel, QualityReportSegmentModel, QualityReportControllerAPI; QualitySummary remains)
3. **GlossaryWorker** — familiar worker pattern from contribution stack
4. **GetSegmentsController** — high business value, moderate difficulty
5. **Remaining Tier 1** — ManageModel (19), SplitJobController (15), ProjectModel (18), Filters (19)
