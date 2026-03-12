<?php

namespace unit\Model\ProjectManager;

use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Teams\TeamStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Collections\RecursiveArrayObject;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Step 11b – Tests for setter / getter / config methods:
 *
 * - setTeam()
 * - _getRequestedFeatures()
 * - getAnalyzeURL()
 * - saveFeaturesInMetadata()
 */
#[AllowMockObjectsWithoutExpectations]
class SettersGettersConfigTest extends AbstractTest
{
    private TestableProjectManager $pm;

    protected function setUp(): void
    {
        $filter   = $this->createStub(MateCatFilter::class);
        $features = $this->createStub(FeatureSet::class);
        $dao      = $this->createStub(MetadataDao::class);
        $logger   = $this->createStub(MatecatLogger::class);

        $this->pm = new TestableProjectManager();
        $this->pm->initForTest($filter, $features, $dao, $logger);
    }

    // ── setTeam() ──────────────────────────────────────────────────

    #[Test]
    public function setTeamStoresTeamStructInProjectStructure(): void
    {
        $team     = new TeamStruct();
        $team->id = 42;

        $this->pm->setTeam($team);

        $ps = $this->pm->getTestProjectStructure();
        $this->assertSame($team, $ps['team']);
        $this->assertSame(42, $ps['id_team']);
    }

    #[Test]
    public function setTeamOverwritesPreviousTeam(): void
    {
        $team1     = new TeamStruct();
        $team1->id = 1;
        $team2     = new TeamStruct();
        $team2->id = 2;

        $this->pm->setTeam($team1);
        $this->pm->setTeam($team2);

        $ps = $this->pm->getTestProjectStructure();
        $this->assertSame($team2, $ps['team']);
        $this->assertSame(2, $ps['id_team']);
    }

    // ── _getRequestedFeatures() ────────────────────────────────────

    #[Test]
    public function getRequestedFeaturesReturnsEmptyWhenNoFeaturesSet(): void
    {
        $this->pm->setProjectStructureValue('project_features', new RecursiveArrayObject());

        $result = $this->pm->callGetRequestedFeatures();

        $this->assertSame([], $result);
    }

    #[Test]
    public function getRequestedFeaturesConvertsRecursiveArrayObjectsToBasicFeatureStructs(): void
    {
        $features = new RecursiveArrayObject([
            new RecursiveArrayObject(['feature_code' => 'translation_versions', 'options' => null]),
            new RecursiveArrayObject(['feature_code' => 'review_extended', 'options' => '{"opt":1}']),
        ]);
        $this->pm->setProjectStructureValue('project_features', $features);

        $result = $this->pm->callGetRequestedFeatures();

        $this->assertCount(2, $result);
        $this->assertInstanceOf(BasicFeatureStruct::class, $result[0]);
        $this->assertInstanceOf(BasicFeatureStruct::class, $result[1]);
        $this->assertSame('translation_versions', $result[0]->feature_code);
        $this->assertSame('review_extended', $result[1]->feature_code);
        $this->assertSame('{"opt":1}', $result[1]->options);
    }

    #[Test]
    public function getRequestedFeaturesSingleFeature(): void
    {
        $features = new RecursiveArrayObject([
            new RecursiveArrayObject(['feature_code' => 'dqf', 'options' => null]),
        ]);
        $this->pm->setProjectStructureValue('project_features', $features);

        $result = $this->pm->callGetRequestedFeatures();

        $this->assertCount(1, $result);
        $this->assertSame('dqf', $result[0]->feature_code);
    }

    // ── getAnalyzeURL() ────────────────────────────────────────────

    #[Test]
    public function getAnalyzeURLBuildsCorrectURLWithHttpHost(): void
    {
        $this->pm->setProjectStructureValue('project_name', 'My Test Project');
        $this->pm->setProjectStructureValue('id_project', 456);
        $this->pm->setProjectStructureValue('ppassword', 'abc123');
        $this->pm->setProjectStructureValue('HTTP_HOST', 'https://example.com');

        $url = $this->pm->getAnalyzeURL();

        $this->assertSame('https://example.com/analyze/my-test-project/456-abc123', $url);
    }

    #[Test]
    public function getAnalyzeURLFallsBackToAppConfigWhenHttpHostIsNull(): void
    {
        $originalHost = AppConfig::$HTTPHOST;

        try {
            AppConfig::$HTTPHOST = 'https://fallback.host';

            $this->pm->setProjectStructureValue('project_name', 'Fallback Project');
            $this->pm->setProjectStructureValue('id_project', 789);
            $this->pm->setProjectStructureValue('ppassword', 'xyz789');
            $this->pm->setProjectStructureValue('HTTP_HOST', null);

            $url = $this->pm->getAnalyzeURL();

            $this->assertSame('https://fallback.host/analyze/fallback-project/789-xyz789', $url);
        } finally {
            AppConfig::$HTTPHOST = $originalHost;
        }
    }

    #[Test]
    public function getAnalyzeURLSlugifiesProjectName(): void
    {
        $this->pm->setProjectStructureValue('project_name', 'Héllo Wörld & Stuff');
        $this->pm->setProjectStructureValue('id_project', 1);
        $this->pm->setProjectStructureValue('ppassword', 'pw');
        $this->pm->setProjectStructureValue('HTTP_HOST', 'https://host.com');

        $url = $this->pm->getAnalyzeURL();

        // friendlySlug lowercases, transliterates, replaces spaces/& with dashes
        $this->assertStringContainsString('/analyze/', $url);
        $this->assertStringEndsWith('/1-pw', $url);
        // The slug should not contain uppercase or &
        $slug = explode('/analyze/', $url)[1];
        $slug = explode('/1-pw', $slug)[0];
        $this->assertDoesNotMatchRegularExpression('/[A-Z&]/', $slug);
    }

    // ── saveFeaturesInMetadata() ───────────────────────────────────

    #[Test]
    public function saveFeaturesInMetadataPersistsWhenCodesExist(): void
    {
        $features = $this->createMock(FeatureSet::class);
        $features->method('getCodes')->willReturn(['translation_versions', 'review_extended']);

        // Re-init with the mock features
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->setProjectStructureValue('id_project', 100);

        $metadataDao = $this->createMock(ProjectsMetadataDao::class);
        $metadataDao->expects($this->once())
            ->method('set')
            ->with(
                100,
                ProjectsMetadataDao::FEATURES_KEY,
                'translation_versions,review_extended',
            );

        $this->pm->setProjectsMetadataDao($metadataDao);
        $this->pm->callSaveFeaturesInMetadata();
    }

    #[Test]
    public function saveFeaturesInMetadataSkipsWhenCodesEmpty(): void
    {
        $features = $this->createMock(FeatureSet::class);
        $features->method('getCodes')->willReturn([]);

        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );

        $metadataDao = $this->createMock(ProjectsMetadataDao::class);
        $metadataDao->expects($this->never())->method('set');

        $this->pm->setProjectsMetadataDao($metadataDao);
        $this->pm->callSaveFeaturesInMetadata();
    }

    #[Test]
    public function saveFeaturesInMetadataSingleCode(): void
    {
        $features = $this->createMock(FeatureSet::class);
        $features->method('getCodes')->willReturn(['dqf']);

        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->setProjectStructureValue('id_project', 55);

        $metadataDao = $this->createMock(ProjectsMetadataDao::class);
        $metadataDao->expects($this->once())
            ->method('set')
            ->with(55, ProjectsMetadataDao::FEATURES_KEY, 'dqf');

        $this->pm->setProjectsMetadataDao($metadataDao);
        $this->pm->callSaveFeaturesInMetadata();
    }
}
