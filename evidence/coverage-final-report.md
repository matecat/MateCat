# Controller Coverage — Final Report

Run wf_f07a9d56-57a (2026-06-16). Gate: aggregate in-scope **80.69%** (>=80 accepted), full suite green, phpstan.neon green. Per-file >=90% = target; <90% accepted with documented reason (mostly external-service / exit() limits under the locked real-DB no-mock pattern).

**43/63 controllers >=90%.**

## >=90% (43)

| Controller | Pattern | Cov% |
|---|---|---|
| API/App/ChangeJobsStatusController.php | real-DB | 100.0 |
| API/App/FilesController.php | real-DB | 100.0 |
| API/App/IntentoController.php | mock | 100.0 |
| API/App/TeamPublicMembersController.php | mock | 100.0 |
| API/V2/ProjectsController.php | real-DB | 100.0 |
| API/V2/ChangeProjectNameController.php | real-DB | 100.0 |
| API/V2/UrlsController.php | mock | 100.0 |
| API/V2/JobsController.php | real-DB | 100.0 |
| API/V2/JobMergeController.php | real-DB | 100.0 |
| API/V2/EnginesController.php | mock | 100.0 |
| API/V3/IssueCheckController.php | mock | 100.0 |
| API/V3/ChunkController.php | real-DB | 100.0 |
| API/V2/ProjectCreationStatusController.php | mock | 100.0 |
| API/App/CompletionEventController.php | real-DB | 100.0 |
| Views/AnalyzeController.php | real-DB | 100.0 |
| API/V3/QualityReportControllerAPI.php | real-DB | 99.4 |
| API/App/GetProjectsController.php | real-DB | 99.0 |
| API/App/ConvertFileController.php | real-DB | 98.0 |
| API/V3/MetaDataController.php | real-DB | 98.0 |
| API/App/EngineController.php | real-DB | 97.7 |
| API/App/SetChunkCompletedController.php | real-DB | 97.1 |
| API/App/SetCurrentSegmentController.php | real-DB | 96.5 |
| API/V2/SegmentVersionController.php | real-DB | 96.5 |
| API/App/SplitSegmentController.php | real-DB | 96.4 |
| API/V2/TeamMembersController.php | real-DB | 96.4 |
| API/App/ApiKeyController.php | real-DB | 96.0 |
| API/V2/TeamsProjectsController.php | real-DB | 95.9 |
| API/V2/TeamsController.php | real-DB | 95.8 |
| API/V2/ActivityLogController.php | real-DB | 95.8 |
| API/V2/ChangePasswordController.php | real-DB | 95.8 |
| API/App/GetTranslationMismatchesController.php | real-DB | 95.2 |
| API/App/JobMetadataController.php | real-DB | 93.9 |
| API/App/SetTranslationController.php | real-DB | 93.5 |
| API/App/OutsourceConfirmationController.php | real-DB | 93.3 |
| API/V2/ChunkTranslationVersionController.php | real-DB | 92.3 |
| API/V2/MarkAllSegmentStatusController.php | real-DB | 92.2 |
| API/App/GetVolumeAnalysisController.php | mock | 91.7 |
| Views/CattoolController.php | view | 91.0 |
| API/V2/ChunkTranslationIssueController.php | real-DB | 90.9 |
| API/V2/CommentsController.php | real-DB | 90.9 |
| API/App/ConnectedServicesController.php | real-DB | 90.5 |
| API/App/CopyAllSourceToTargetController.php | real-DB | 90.3 |
| API/V2/UserController.php | real-DB | 90.2 |

## <90% — accepted with reason (20)

| Controller | Cov% | Why <90% |
|---|---|---|
| Views/ActivityLogController.php | 88.6 | dead/defensive branch |
| API/App/UpdateJobKeysController.php | 88.2 | external service (MyMemory/MT/email) — locked no-mock pattern |
| API/V2/ReviseTranslationIssuesController.php | 88.2 | external service (MyMemory/MT/email) — locked no-mock pattern |
| API/V3/TmKeyManagementController.php | 88.2 | exit() — uncatchable, needs process isolation |
| API/V2/MemoryKeysController.php | 85.7 | partially liftable |
| API/V3/TeamsProjectsController.php | 84.9 | exit() — uncatchable, needs process isolation |
| API/App/CreateProjectController.php | 82.1 | external service (MyMemory/MT/email) — locked no-mock pattern |
| API/V1/NewController.php | 78.1 | external service (MyMemory/MT/email) — locked no-mock pattern |
| API/App/TmKeyManagementController.php | 73.5 | exit() — uncatchable, needs process isolation |
| API/GDrive/GDriveController.php | 73.2 | external service (MyMemory/MT/email) — locked no-mock pattern |
| API/V2/ReviewsController.php | 70.0 | partially liftable |
| API/App/AjaxUtilsController.php | 69.6 | external service (MyMemory/MT/email) — locked no-mock pattern |
| API/V2/JobsTranslatorsController.php | 65.1 | external service (MyMemory/MT/email) — locked no-mock pattern |
| API/V3/MyMemoryController.php | 52.0 | external service (MyMemory/MT/email) — locked no-mock pattern |
| API/App/GetTagProjectionController.php | 50.8 | external service (MyMemory/MT/email) — locked no-mock pattern |
| API/App/UserKeysController.php | 48.4 | external service (MyMemory/MT/email) — locked no-mock pattern |
| API/V2/DownloadJobTMXController.php | 41.8 | exit() — uncatchable, needs process isolation |
| API/App/TMXFileController.php | 37.5 | external service (MyMemory/MT/email) — locked no-mock pattern |
| API/V3/StatusController.php | 28.6 | partially liftable |
| API/V2/DownloadController.php | 15.8 | exit() — uncatchable, needs process isolation |
