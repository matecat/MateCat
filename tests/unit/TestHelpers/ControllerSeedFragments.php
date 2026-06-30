<?php

declare(strict_types=1);

namespace Matecat\TestHelpers;

use Model\DataAccess\Database;
use PDO;

/**
 * Constraint-correct INSERT fragments for the ~15 tables the in-scope
 * controllers touch (controller-coverage-afterconstruct plan, Wave 0 W0.4).
 *
 * Every fragment is NOT-NULL / FK complete (columns derived from the live
 * `unittest_matecat_local` schema via SHOW CREATE TABLE) and is parameterized
 * by an ID-block base plus a per-suite unique owner email (Playbook §4).
 *
 * Usage (real-DB Testable suites):
 *   use \Matecat\TestHelpers\ControllerSeedFragments;
 *   private const int BASE = 9_000_000 + (N * 1000); // N = task number
 *   ...
 *   $owner = $this->ownerEmail(self::BASE);
 *   $this->seedProject(self::BASE, $owner);
 *   $this->seedFile(self::BASE);
 *   $this->seedJob(self::BASE, $owner);
 *
 * ID convention inside a reserved 1000-block (base = 9_000_000 + N*1000):
 *   base+1 project, base+2 job, base+3 segment, base+4 file,
 *   base+5 team, base+6 user/uid, base+7 qa_model,
 *   base+8 qa_chunk_review, base+9 segment_translation_version,
 *   base+10 comment, base+11 connected_service, base+12 teams_users row,
 *   base+13 translator-profile-ish ids.
 *
 * Clean ONLY by reserved id (see {@see cleanFragments()}); NEVER by shared
 * keys (id_customer, owner, email, hash) — that wipes sibling suites' rows.
 *
 * All inserts use INSERT IGNORE so a leaked row from a crashed prior run does
 * not fatal the suite; pair with {@see cleanFragments()} in setUp() before
 * seeding and in tearDown() after.
 */
trait ControllerSeedFragments
{
    /**
     * Per-suite unique owner / customer identifier derived from the ID block.
     * Use this instead of the shared test@example.org so concurrent suites do
     * not collide on projects.id_customer / jobs.owner (Playbook §4).
     */
    protected function ownerEmail(int $base): string
    {
        return 'ctrltest_' . $base . '@example.org';
    }

    protected function seedConnection(): PDO
    {
        return obtainTestDatabase()->getConnection();
    }

    // ─── id helpers (deterministic offsets inside the reserved block) ───

    protected function projectId(int $base): int
    {
        return $base + 1;
    }

    protected function jobId(int $base): int
    {
        return $base + 2;
    }

    protected function segmentId(int $base): int
    {
        return $base + 3;
    }

    protected function fileId(int $base): int
    {
        return $base + 4;
    }

    protected function teamId(int $base): int
    {
        return $base + 5;
    }

    protected function userId(int $base): int
    {
        return $base + 6;
    }

    protected function qaModelId(int $base): int
    {
        return $base + 7;
    }

    protected function chunkReviewId(int $base): int
    {
        return $base + 8;
    }

    protected function versionId(int $base): int
    {
        return $base + 9;
    }

    protected function commentId(int $base): int
    {
        return $base + 10;
    }

    protected function connectedServiceId(int $base): int
    {
        return $base + 11;
    }

    protected function teamUserId(int $base): int
    {
        return $base + 12;
    }

    // ─── seed fragments (one per in-scope table) ───

    protected function seedProject(int $base, string $owner, string $password = 'projpw'): void
    {
        $id = $this->projectId($base);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO projects (id, id_customer, password, name, create_date, status_analysis, id_team) "
            . "VALUES ($id, '$owner', '$password', 'CtrlTestProject_$base', NOW(), 'DONE', " . $this->teamId($base) . ")"
        );
    }

    protected function seedFile(int $base): void
    {
        $id        = $this->fileId($base);
        $projectId = $this->projectId($base);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO files (id, id_project, filename, source_language, mime_type) "
            . "VALUES ($id, $projectId, 'ctrltest_$base.xliff', 'en-US', 'application/xliff+xml')"
        );
    }

    protected function seedJob(int $base, string $owner, string $password = 'jobpw', string $status = 'active'): void
    {
        $id        = $this->jobId($base);
        $projectId = $this->projectId($base);
        $segmentId = $this->segmentId($base);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO jobs (id, password, id_project, source, target, job_first_segment, job_last_segment, "
            . "owner, tm_keys, create_date, disabled, status) "
            . "VALUES ($id, '$password', $projectId, 'en-US', 'it-IT', $segmentId, $segmentId, "
            . "'$owner', '[]', NOW(), 0, '$status')"
        );
    }

    protected function seedSegment(int $base, string $hashSuffix = ''): void
    {
        $id     = $this->segmentId($base);
        $fileId = $this->fileId($base);
        $hash   = 'ctrltest_hash_' . $base . $hashSuffix;
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count, show_in_cattool) "
            . "VALUES ($id, $fileId, '1', 'Hello world', '$hash', 2, 1)"
        );
    }

    protected function seedSegmentTranslation(int $base, string $status = 'TRANSLATED', string $translation = 'Ciao mondo'): void
    {
        $segmentId = $this->segmentId($base);
        $jobId     = $this->jobId($base);
        $hash      = 'ctrltest_hash_' . $base;
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO segment_translations (id_segment, id_job, segment_hash, translation, status, version_number, translation_date) "
            . "VALUES ($segmentId, $jobId, '$hash', '$translation', '$status', 0, NOW())"
        );
    }

    protected function seedTeam(int $base, string $type = 'personal'): void
    {
        $id        = $this->teamId($base);
        $createdBy = $this->userId($base);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO teams (id, name, created_by, created_at, type) "
            . "VALUES ($id, 'CtrlTestTeam_$base', $createdBy, NOW(), '$type')"
        );
    }

    protected function seedMembership(int $base, bool $isAdmin = true): void
    {
        $id     = $this->teamUserId($base);
        $teamId = $this->teamId($base);
        $uid    = $this->userId($base);
        $admin  = $isAdmin ? 1 : 0;
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO teams_users (id, id_team, uid, is_admin) "
            . "VALUES ($id, $teamId, $uid, $admin)"
        );
    }

    protected function seedUser(int $base, ?string $email = null): void
    {
        $uid   = $this->userId($base);
        $email = $email ?? ('ctrluser_' . $base . '@example.org');
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO users (uid, email, create_date, first_name, last_name) "
            . "VALUES ($uid, '$email', NOW(), 'Ctrl', 'Tester')"
        );
    }

    protected function seedQaModel(int $base, string $label = 'CtrlTestModel'): void
    {
        $id  = $this->qaModelId($base);
        $uid = $this->userId($base);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO qa_models (id, uid, create_date, label, pass_type, pass_options) "
            . "VALUES ($id, $uid, NOW(), '$label', 'standard', '{}')"
        );
    }

    protected function seedChunkReview(int $base, string $password = 'jobpw', string $reviewPassword = 'revpw', int $sourcePage = 2): void
    {
        $id        = $this->chunkReviewId($base);
        $projectId = $this->projectId($base);
        $jobId     = $this->jobId($base);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO qa_chunk_reviews (id, id_project, id_job, password, review_password, source_page) "
            . "VALUES ($id, $projectId, $jobId, '$password', '$reviewPassword', $sourcePage)"
        );
    }

    protected function seedSegmentTranslationVersion(int $base, int $versionNumber = 1, string $translation = 'Ciao mondo v1'): void
    {
        $id        = $this->versionId($base);
        $segmentId = $this->segmentId($base);
        $jobId     = $this->jobId($base);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO segment_translation_versions (id, id_segment, id_job, translation, version_number, creation_date) "
            . "VALUES ($id, $segmentId, $jobId, '$translation', $versionNumber, NOW())"
        );
    }

    protected function seedComment(int $base, string $message = 'Ctrl test comment', int $messageType = 2): void
    {
        $id        = $this->commentId($base);
        $jobId     = $this->jobId($base);
        $segmentId = $this->segmentId($base);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO comments (id, id_job, id_segment, create_date, email, full_name, uid, source_page, is_anonymous, message_type, message) "
            . "VALUES ($id, $jobId, $segmentId, NOW(), 'ctrluser_$base@example.org', 'Ctrl Tester', " . $this->userId($base) . ", 1, 0, $messageType, '$message')"
        );
    }

    protected function seedConnectedService(int $base, string $service = 'gdrive', bool $isDefault = true): void
    {
        $id        = $this->connectedServiceId($base);
        $uid       = $this->userId($base);
        $email     = 'ctrluser_' . $base . '@example.org';
        $isDefault = $isDefault ? 1 : 0;
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO connected_services (id, uid, service, remote_id, name, email, oauth_access_token, created_at, is_default) "
            . "VALUES ($id, $uid, '$service', 'remote_$base', 'CtrlService_$base', '$email', 'tok_$base', NOW(), $isDefault)"
        );
    }

    /**
     * job_keys / user TM-glossary keys live in the `memory_keys` table
     * (there is no literal `job_keys` table; jobs.tm_keys is JSON on the job
     * row). Keyed by (uid, key_value) — both supplied from the block.
     */
    protected function seedJobKey(int $base, ?string $keyValue = null): void
    {
        $uid      = $this->userId($base);
        $keyValue = $keyValue ?? ('ctrltestkey' . $base);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO memory_keys (uid, key_value, key_name, key_tm, key_glos, creation_date) "
            . "VALUES ($uid, '$keyValue', 'CtrlTestKey_$base', 1, 1, NOW())"
        );
    }

    protected function seedTranslator(int $base, ?string $username = null, ?string $email = null): void
    {
        $username = $username ?? ('ctrltrans_' . $base);
        $email    = $email ?? ('ctrltrans_' . $base . '@example.org');
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO translators (username, email, first_name, last_name, mymemory_api_key) "
            . "VALUES ('$username', '$email', 'Ctrl', 'Translator', 'mmkey_$base')"
        );
    }

    /**
     * Clean ONLY the rows this block reserved, in FK-safe order. Call FIRST in
     * setUp() (before seeding) and in tearDown(). Never deletes by shared keys.
     */
    protected function cleanFragments(int $base): void
    {
        $conn = $this->seedConnection();

        $conn->exec("DELETE FROM comments WHERE id = " . $this->commentId($base));
        $conn->exec("DELETE FROM segment_translation_versions WHERE id = " . $this->versionId($base));
        $conn->exec("DELETE FROM qa_chunk_reviews WHERE id = " . $this->chunkReviewId($base));
        $conn->exec("DELETE FROM connected_services WHERE id = " . $this->connectedServiceId($base));
        $conn->exec("DELETE FROM segment_translations WHERE id_job = " . $this->jobId($base));
        $conn->exec("DELETE FROM segments WHERE id = " . $this->segmentId($base));
        $conn->exec("DELETE FROM files WHERE id = " . $this->fileId($base));
        $conn->exec("DELETE FROM jobs WHERE id = " . $this->jobId($base));
        $conn->exec("DELETE FROM qa_models WHERE id = " . $this->qaModelId($base));
        $conn->exec("DELETE FROM projects WHERE id = " . $this->projectId($base));
        $conn->exec("DELETE FROM teams_users WHERE id = " . $this->teamUserId($base));
        $conn->exec("DELETE FROM teams WHERE id = " . $this->teamId($base));
        $conn->exec("DELETE FROM memory_keys WHERE uid = " . $this->userId($base));
        $conn->exec("DELETE FROM users WHERE uid = " . $this->userId($base));
        $conn->exec("DELETE FROM translators WHERE username = 'ctrltrans_$base'");
    }
}
