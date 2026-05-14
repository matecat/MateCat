# DAO Coverage Backlog

**Date**: 2026-05-14
**Goal**: Raise test coverage to ≥80% lines for each DAO file listed below.

All files are **PHPStan clean** (0 errors without baseline). They were modified during the `fetchById` migration and subsequent PHPStan fixes but their test coverage remains below the 80% target.

Once a file reaches ≥80% coverage, move it to the main ledger in `docs/phpstan-baseline-reduction-progression.md`.

## Queue

| File | Current Coverage | Lines (covered/total) | Priority |
|------|-----------------|----------------------|----------|
| `lib/Model/Comments/CommentDao.php` | 74.40% | 93/125 | High |
| `lib/Model/LQA/EntryCommentDao.php` | 63.64% | 28/44 | Medium |
| `lib/Model/Files/FilesPartsDao.php` | 60.61% | 20/33 | Medium |
| `lib/Model/Teams/TeamDao.php` | 51.02% | 50/98 | Medium |
| `lib/Model/ApiKeys/ApiKeyDao.php` | 50.00% | 15/30 | Medium |
| `lib/Model/LQA/ChunkReviewDao.php` | 41.55% | 91/219 | Low |
| `lib/Model/LQA/CategoryDao.php` | 31.31% | 31/99 | Low |
| `lib/Model/Jobs/JobDao.php` | 29.35% | 81/276 | Low |
| `lib/Model/Segments/SegmentDao.php` | 25.24% | 80/317 | Low |
| `lib/Model/Translations/SegmentTranslationDao.php` | 25.37% | 86/339 | Low |
| `lib/Model/Analysis/AnalysisDao.php` | ~0% | — | Low |
| `lib/Model/ConnectedServices/ConnectedServiceDao.php` | ~0% | — | Low |
| `lib/Model/Files/FileDao.php` | ~0% | — | Low |

## Notes

- **Priority = High**: Files closest to 80% — small effort for big gain.
- **Priority = Medium**: 50–65% — moderate effort needed.
- **Priority = Low**: <50% or ~0% — significant test infrastructure needed.
- Coverage measured with full test suite (`php vendor/bin/phpunit --exclude-group=ExternalServices`).
- All 18 modified DAOs are PHPStan clean as of 2026-05-14.
