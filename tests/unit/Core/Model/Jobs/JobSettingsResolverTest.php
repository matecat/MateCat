<?php

namespace Matecat\Core\Model\Jobs;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Jobs\JobSettingsResolver;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\Jobs\MetadataStruct;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\Test;

/**
 * The fallback chain the whole feature rests on: an MT setting is read from the job first, and only
 * a job that has no row of its own falls back to project metadata.
 *
 * Production cannot be migrated — billions of project_metadata rows — so the project read is not a
 * deprecation path but the permanent answer for every project created before the settings moved.
 * These tests pin that, plus the two engine-config quirks the resolver absorbs.
 */
#[AllowMockObjectsWithoutExpectations]
class JobSettingsResolverTest extends AbstractTest
{
    private const int JOB_ID = 42;
    private const string PASSWORD = 'jobpass';
    private const int PROJECT_ID = 999;
    private const string KEY = 'deepl_formality';

    private JobsMetadataDao&MockObject $jobDao;
    private ProjectsMetadataDao&MockObject $projectDao;
    private JobSettingsResolver $resolver;

    public function setUp(): void
    {
        parent::setUp();

        $this->jobDao = $this->createMock(JobsMetadataDao::class);
        $this->projectDao = $this->createMock(ProjectsMetadataDao::class);
        // setCacheTTL() is fluent on the real DAO.
        $this->projectDao->method('setCacheTTL')->willReturnSelf();

        $this->resolver = new JobSettingsResolver(
            $this->createStub(IDatabase::class),
            $this->jobDao,
            $this->projectDao
        );
    }

    private function struct(string $key, mixed $value): MetadataStruct
    {
        $struct = new MetadataStruct();
        $struct->id_job = self::JOB_ID;
        $struct->password = self::PASSWORD;
        $struct->key = $key;
        $struct->value = $value;

        return $struct;
    }

    // =========================================================================
    // resolve() — the fallback chain
    // =========================================================================

    #[Test]
    public function jobValueWinsAndTheProjectIsNeverRead(): void
    {
        $this->jobDao->method('get')->willReturn($this->struct(self::KEY, 'prefer_more'));
        $this->projectDao->expects($this->never())->method('getValue');

        $this->assertSame(
            'prefer_more',
            $this->resolver->resolve(self::JOB_ID, self::PASSWORD, self::PROJECT_ID, self::KEY)
        );
    }

    #[Test]
    public function aJobWithoutItsOwnRowFallsBackToTheProject(): void
    {
        $this->jobDao->method('get')->willReturn(null);
        $this->projectDao->method('getValue')->willReturn('prefer_less');

        $this->assertSame(
            'prefer_less',
            $this->resolver->resolve(self::JOB_ID, self::PASSWORD, self::PROJECT_ID, self::KEY)
        );
    }

    #[Test]
    public function neitherScopeHavingTheKeyResolvesToNull(): void
    {
        $this->jobDao->method('get')->willReturn(null);
        $this->projectDao->method('getValue')->willReturn(null);

        $this->assertNull($this->resolver->resolve(self::JOB_ID, self::PASSWORD, self::PROJECT_ID, self::KEY));
    }

    #[Test]
    public function theProjectIsQueriedForTheKeyAndProjectAsked(): void
    {
        $this->jobDao->method('get')->willReturn(null);
        $this->projectDao->expects($this->once())
            ->method('getValue')
            ->with(self::PROJECT_ID, self::KEY)
            ->willReturn('default');

        $this->resolver->resolve(self::JOB_ID, self::PASSWORD, self::PROJECT_ID, self::KEY);
    }

    #[Test]
    public function theJobValueIsUnMarshalledLikeAnyOtherJobMetadataRow(): void
    {
        // Stored as a string, read back as the int the callers apply arithmetic to.
        $this->jobDao->method('get')->willReturn(
            $this->struct(JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value, '90')
        );

        $this->assertSame(
            90,
            $this->resolver->resolve(
                self::JOB_ID,
                self::PASSWORD,
                self::PROJECT_ID,
                JobsMetadataMarshaller::MT_QUALITY_VALUE_IN_EDITOR->value
            )
        );
    }

    #[Test]
    public function anEmptyJobRowStillWinsOverTheProject(): void
    {
        // Deliberate: creation never writes an empty row precisely because a present-but-empty row
        // is an answer, not an absence. If one ever exists it must not silently resurrect the
        // project value.
        $this->jobDao->method('get')->willReturn($this->struct(self::KEY, ''));
        $this->projectDao->expects($this->never())->method('getValue');

        $this->assertSame(
            '',
            $this->resolver->resolve(self::JOB_ID, self::PASSWORD, self::PROJECT_ID, self::KEY)
        );
    }

    // =========================================================================
    // resolve() — callers without a full job context
    // =========================================================================

    #[Test]
    public function withoutAJobIdOnlyTheProjectIsRead(): void
    {
        $this->jobDao->expects($this->never())->method('get');
        $this->projectDao->method('getValue')->willReturn('default');

        $this->assertSame(
            'default',
            $this->resolver->resolve(null, self::PASSWORD, self::PROJECT_ID, self::KEY)
        );
    }

    #[Test]
    public function withoutAPasswordOnlyTheProjectIsRead(): void
    {
        // The password is half the job_metadata key, so an id on its own cannot address a row.
        $this->jobDao->expects($this->never())->method('get');
        $this->projectDao->method('getValue')->willReturn('default');

        $this->assertSame(
            'default',
            $this->resolver->resolve(self::JOB_ID, null, self::PROJECT_ID, self::KEY)
        );
    }

    #[Test]
    public function aNullProjectIdSkipsTheFallbackEntirely(): void
    {
        // Used by the analysis publisher, which already holds the project value read from the master
        // node and must not have it replaced by a cached read.
        $this->jobDao->method('get')->willReturn(null);
        $this->projectDao->expects($this->never())->method('getValue');

        $this->assertNull($this->resolver->resolve(self::JOB_ID, self::PASSWORD, null, self::KEY));
    }

    #[Test]
    public function aNegativeProjectIdSkipsTheFallbackEntirely(): void
    {
        // DeepLEngineValidator fabricates a negative pid so that validating an engine picks up no
        // stored setting at all.
        $this->jobDao->method('get')->willReturn(null);
        $this->projectDao->expects($this->never())->method('getValue');

        $this->assertNull($this->resolver->resolve(null, null, -12345, self::KEY));
    }

    // =========================================================================
    // resolveMany()
    // =========================================================================

    #[Test]
    public function resolveManyLetsTheJobOverrideKeyByKey(): void
    {
        $this->projectDao->method('allByProjectIdAsKeyValue')->willReturn([
            'deepl_formality' => 'default',
            'deepl_engine_type' => 'latency_optimized',
            // Not asked for: must not leak into the result.
            'icu_enabled' => '1',
        ]);
        $this->jobDao->method('getByJobIdAndPassword')->willReturn([
            $this->struct('deepl_formality', 'prefer_more'),
        ]);

        $resolved = $this->resolver->resolveMany(
            self::JOB_ID,
            self::PASSWORD,
            self::PROJECT_ID,
            ['deepl_formality', 'deepl_engine_type']
        );

        $this->assertSame(
            ['deepl_formality' => 'prefer_more', 'deepl_engine_type' => 'latency_optimized'],
            $resolved
        );
    }

    #[Test]
    public function resolveManyOmitsKeysNeitherScopeHas(): void
    {
        // Absent rather than null, so the caller's own `?? default` still applies.
        $this->projectDao->method('allByProjectIdAsKeyValue')->willReturn([]);
        $this->jobDao->method('getByJobIdAndPassword')->willReturn([]);

        $this->assertSame(
            [],
            $this->resolver->resolveMany(self::JOB_ID, self::PASSWORD, self::PROJECT_ID, ['deepl_formality'])
        );
    }

    #[Test]
    public function resolveManyWithoutAJobContextReadsTheProjectOnly(): void
    {
        $this->jobDao->expects($this->never())->method('getByJobIdAndPassword');
        $this->projectDao->method('allByProjectIdAsKeyValue')->willReturn(['lara_style' => 'faithful']);

        $this->assertSame(
            ['lara_style' => 'faithful'],
            $this->resolver->resolveMany(null, null, self::PROJECT_ID, ['lara_style'])
        );
    }

    #[Test]
    public function resolveManyWithoutAProjectReadsTheJobOnly(): void
    {
        $this->projectDao->expects($this->never())->method('allByProjectIdAsKeyValue');
        $this->jobDao->method('getByJobIdAndPassword')->willReturn([
            $this->struct('lara_style', 'creative'),
        ]);

        $this->assertSame(
            ['lara_style' => 'creative'],
            $this->resolver->resolveMany(self::JOB_ID, self::PASSWORD, null, ['lara_style'])
        );
    }

    // =========================================================================
    // Engine $_config helpers
    // =========================================================================

    #[Test]
    public function engineConfigResolvesFromIdProject(): void
    {
        $this->jobDao->method('get')->willReturn(null);
        $this->projectDao->expects($this->once())
            ->method('getValue')
            ->with(self::PROJECT_ID, self::KEY)
            ->willReturn('default');

        $this->resolver->resolveFromEngineConfig(['id_project' => self::PROJECT_ID], self::KEY);
    }

    #[Test]
    public function engineConfigFallsBackToPidWhenIdProjectIsAbsent(): void
    {
        // DeepL and Intento are handed `pid`, Lara and MMT `id_project`. Before this the two halves
        // silently resolved nothing when a caller populated only the other one.
        $this->jobDao->method('get')->willReturn(null);
        $this->projectDao->expects($this->once())
            ->method('getValue')
            ->with(self::PROJECT_ID, self::KEY)
            ->willReturn('default');

        $this->resolver->resolveFromEngineConfig(['pid' => self::PROJECT_ID], self::KEY);
    }

    #[Test]
    public function engineConfigPrefersIdProjectOverPid(): void
    {
        $this->jobDao->method('get')->willReturn(null);
        $this->projectDao->expects($this->once())
            ->method('getValue')
            ->with(self::PROJECT_ID, self::KEY)
            ->willReturn('default');

        $this->resolver->resolveFromEngineConfig(
            ['id_project' => self::PROJECT_ID, 'pid' => 7],
            self::KEY
        );
    }

    #[Test]
    public function engineConfigUsesTheJobCredentialWhenBothHalvesArePresent(): void
    {
        $this->jobDao->expects($this->once())
            ->method('get')
            ->with(self::JOB_ID, self::PASSWORD, self::KEY, JobSettingsResolver::DEFAULT_TTL)
            ->willReturn($this->struct(self::KEY, 'prefer_more'));

        $this->assertSame('prefer_more', $this->resolver->resolveFromEngineConfig([
            'job_id' => self::JOB_ID,
            'job_password' => self::PASSWORD,
            'pid' => self::PROJECT_ID,
        ], self::KEY));
    }

    #[Test]
    public function engineConfigWithoutAJobPasswordReadsTheProjectOnly(): void
    {
        // The analysis TMS path builds a config with job_id but no password; it must degrade to the
        // project value rather than address a row with an empty password.
        $this->jobDao->expects($this->never())->method('get');
        $this->projectDao->method('getValue')->willReturn('default');

        $this->assertSame('default', $this->resolver->resolveFromEngineConfig([
            'job_id' => self::JOB_ID,
            'pid' => self::PROJECT_ID,
        ], self::KEY));
    }

    #[Test]
    public function engineConfigWithNoProjectAtAllResolvesToNull(): void
    {
        $this->jobDao->expects($this->never())->method('get');
        $this->projectDao->expects($this->never())->method('getValue');

        $this->assertNull($this->resolver->resolveFromEngineConfig([], self::KEY));
    }

    #[Test]
    public function engineConfigResolveManyAppliesTheSameProjectIdHandling(): void
    {
        $this->jobDao->method('getByJobIdAndPassword')->willReturn([]);
        $this->projectDao->expects($this->once())
            ->method('allByProjectIdAsKeyValue')
            ->with(self::PROJECT_ID)
            ->willReturn(['deepl_formality' => 'default']);

        $this->assertSame(
            ['deepl_formality' => 'default'],
            $this->resolver->resolveManyFromEngineConfig(['pid' => self::PROJECT_ID], ['deepl_formality'])
        );
    }

    // =========================================================================
    // Caching
    // =========================================================================

    #[Test]
    public function theTtlIsForwardedToBothScopes(): void
    {
        $this->jobDao->expects($this->once())
            ->method('get')
            ->with(self::JOB_ID, self::PASSWORD, self::KEY, 3600)
            ->willReturn(null);
        $this->projectDao->expects($this->once())
            ->method('setCacheTTL')
            ->with(3600)
            ->willReturnSelf();

        $this->resolver->resolve(self::JOB_ID, self::PASSWORD, self::PROJECT_ID, self::KEY, 3600);
    }

    #[Test]
    public function theDefaultTtlIsTheOneTheEnginesAlreadyUsed(): void
    {
        $this->assertSame(86400, JobSettingsResolver::DEFAULT_TTL);
    }
}
