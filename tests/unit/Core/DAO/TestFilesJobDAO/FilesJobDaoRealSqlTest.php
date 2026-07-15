<?php

namespace Matecat\Core\DAO\TestFilesJobDAO;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Files\FilesJobDao;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class FilesJobDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private FilesJobDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startRealSql(['files_parts', 'files_job', 'files', 'jobs', 'projects']);
        $this->dao = new FilesJobDao($this->realSqlDb());
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    #[Test]
    public function isFilePartInJobReturnsTrueForAPartBelongingToTheJob(): void
    {
        $project = $this->fixtures->makeProject();
        $job     = $this->fixtures->makeJob($project['id']);
        $file    = $this->fixtures->makeFile($project['id']);
        $this->fixtures->makeFilesJob($job['id'], $file['id']);
        $part = $this->fixtures->makeFilesPart($file['id']);

        self::assertTrue($this->dao->isFilePartInJob($part['id'], $job['id'], 0));
    }

    #[Test]
    public function isFilePartInJobReturnsFalseForAPartBelongingToAnotherJob(): void
    {
        // the caller's authenticated chunk
        $projectA = $this->fixtures->makeProject();
        $jobA     = $this->fixtures->makeJob($projectA['id']);

        // another tenant's project/job/file/part
        $projectB = $this->fixtures->makeProject();
        $jobB     = $this->fixtures->makeJob($projectB['id']);
        $fileB    = $this->fixtures->makeFile($projectB['id']);
        $this->fixtures->makeFilesJob($jobB['id'], $fileB['id']);
        $partB = $this->fixtures->makeFilesPart($fileB['id']);

        // authenticated to jobA, probing jobB's file part → must be denied (IDOR guard)
        self::assertFalse($this->dao->isFilePartInJob($partB['id'], $jobA['id'], 0));
    }

    #[Test]
    public function isFilePartInJobReturnsFalseForANonExistentPart(): void
    {
        $project = $this->fixtures->makeProject();
        $job     = $this->fixtures->makeJob($project['id']);

        self::assertFalse($this->dao->isFilePartInJob(999999999, $job['id'], 0));
    }
}
