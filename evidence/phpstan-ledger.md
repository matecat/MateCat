# PHPStan Baseline Reduction — Fixed Files Ledger

Branch: `fix-database-obtain`
Coverage target: ≥90% per file

Each file listed here is fully clean (0 errors, no-baseline) and must remain so.
Any change that introduces a new PHPStan error in a ledger file MUST be fixed, not baselined.

## Ledger

| File | Cleaned | Coverage |
|------|---------|----------|
| `lib/Controller/API/App/Authentication/LaraAuthController.php` | 2026-06-30 | pre-existing |
| `lib/Controller/Views/OutsourceTo/AbstractController.php` | 2026-06-30 | 91.04% (refactor: setTemplateVars folded into setView; createLogger/createShopCart seams) |
| `lib/Controller/API/Commons/Validators/ChunkPasswordValidator.php` | 2026-06-30 | 100% |
| `lib/Controller/API/Commons/Validators/ProjectValidator.php` | 2026-06-30 | 96% |
| `lib/Controller/API/V2/ProjectCompletionStatus.php` | 2026-06-30 | 96% (cascade) |
| `lib/Controller/API/V2/SegmentTranslationIssueController.php` | 2026-06-30 | pre-existing |
