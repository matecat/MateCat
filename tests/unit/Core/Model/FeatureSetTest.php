<?php

namespace Matecat\Core\Model;

use Exception;
use LogicException;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;

class FeatureSetTest extends AbstractTest
{
    #[Test]
    public function getSortedFeatures(): void
    {
        // Real dependency chain here (verified against actual class declarations):
        // translation_versions -> review_extended (AbstractRevisionFeature's own
        // default, inherited by ReviewExtended) -> second_pass_review (its own
        // override). translated/mmt/aligner/project_completion have no declared
        // dependency on anything, so their relative position is unconstrained —
        // asserting an exact full order over-specifies the contract (see report
        // §9.2). Assert the real invariant instead.
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));
        $featureSet->loadFromString("translation_versions,project_completion");

        $codes    = $featureSet->sortFeatures()->getCodes();
        $position = array_flip($codes);

        self::assertEqualsCanonicalizing(
            ['translated', 'mmt', 'translation_versions', 'review_extended', 'second_pass_review', 'aligner', 'project_completion'],
            $codes
        );
        self::assertLessThan(
            $position['review_extended'],
            $position['translation_versions'],
            'translation_versions must load before review_extended, since review_extended depends on it'
        );
        self::assertLessThan(
            $position['second_pass_review'],
            $position['review_extended'],
            'review_extended must load before second_pass_review, since second_pass_review depends on it'
        );
    }

    #[Test]
    public function sortFeaturesPreservesOriginalOrderWhenNoDependenciesAreDeclared(): void
    {
        // TDD boundary for report §9.2 (`quickSort()` rename/cleanup): every shipped
        // feature declares zero dependencies today, so sortFeatures() must be a stable
        // no-op over insertion order. This is the safety net that must stay green
        // through a pure rename — and would have caught both bad attempts we just
        // rejected (one silently reversed the list, one crashed on uninitialized
        // struct properties).
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class), [
            new BasicFeatureStruct(['feature_code' => 'test_boundary_a']),
            new BasicFeatureStruct(['feature_code' => 'test_boundary_b']),
            new BasicFeatureStruct(['feature_code' => 'test_boundary_c']),
        ]);

        $actual = $featureSet->sortFeatures()->getCodes();
        self::assertSame(
            ['test_boundary_a', 'test_boundary_b', 'test_boundary_c'],
            $actual
        );
    }

    #[Test]
    public function getFeaturesStructsReturnsLoadedFeatures(): void
    {
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class), [
            new BasicFeatureStruct(['feature_code' => 'test_featureset_stub_a']),
        ]);

        $structs = $featureSet->getFeaturesStructs();

        self::assertCount(1, $structs);
        self::assertSame('test_featureset_stub_a', $structs['test_featureset_stub_a']->feature_code);
    }

    #[Test]
    public function loadProjectDependenciesFromProjectMetadataIsNoOp(): void
    {
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class), [
            new BasicFeatureStruct(['feature_code' => 'test_featureset_stub_a']),
        ]);

        $featureSet->loadProjectDependenciesFromProjectMetadata(['some_key' => 'some_value']);

        self::assertSame(
            ['test_featureset_stub_a'],
            $featureSet->getCodes()
        );
    }

    #[Test]
    public function loadForProjectClearsAndReloadsFeatures(): void
    {
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class), [
            new BasicFeatureStruct(['feature_code' => 'test_featureset_stub_a']),
        ]);

        $metadataDao = $this->createStub(MetadataDao::class);
        $metadataDao->method('setCacheTTL')->willReturnSelf();
        $metadataDao->method('getValue')->willReturn('');

        $project = new ProjectStruct();
        $project->id = 1;

        $featureSet->loadForProject($project, $metadataDao);

        $codes = $featureSet->getCodes();
        self::assertNotContains('test_featureset_stub_a', $codes);
    }

    #[Test]
    public function loadForProjectLoadsMetadataFeatures(): void
    {
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));

        $metadataDao = $this->createStub(MetadataDao::class);
        $metadataDao->method('setCacheTTL')->willReturnSelf();
        $metadataDao->method('getValue')->willReturn('translation_versions');

        $project = new ProjectStruct();
        $project->id = 1;

        $featureSet->loadForProject($project, $metadataDao);

        $codes = $featureSet->getCodes();
        self::assertContains('translation_versions', $codes);
    }

    #[Test]
    public function mergeThrowsOnConflictingDependencies(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/conflicting/i');

        new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class), [
            new BasicFeatureStruct(['feature_code' => 'test_feature_conflict_declarer']),
            new BasicFeatureStruct(['feature_code' => 'test_featureset_stub_a']),
        ]);
    }

    #[Test]
    public function sortFeaturesOrdersTransitiveDependenciesCorrectly(): void
    {
        // RED boundary for report §9.2: real transitive dependencies (A needs B needs
        // C) must place C before B and B before A. Every quickSort() variant tried so
        // far (the original direct-only pivot check, the array_fill_keys detour, the
        // array_combine detour) only checks one hop and fails this — it takes a real
        // topological sort (e.g. Kahn's algorithm) to get transitive chains right.
        // D is an unrelated feature with no dependencies: it must be present, but its
        // exact position is unconstrained and must not be asserted.
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class), [
            new BasicFeatureStruct(['feature_code' => 'test_topo_sort_a']),
            new BasicFeatureStruct(['feature_code' => 'test_topo_sort_d']),
            new BasicFeatureStruct(['feature_code' => 'test_topo_sort_b']),
            new BasicFeatureStruct(['feature_code' => 'test_topo_sort_c']),
        ]);

        $actual = $featureSet->sortFeatures()->getCodes();
        $position = array_flip($actual);

        self::assertContains('test_topo_sort_d', $actual);
        self::assertLessThan(
            $position['test_topo_sort_b'],
            $position['test_topo_sort_c'],
            'C must load before B, since B depends on C'
        );
        self::assertLessThan(
            $position['test_topo_sort_a'],
            $position['test_topo_sort_b'],
            'B must load before A, since A depends on B'
        );
    }

    #[Test]
    public function sortFeaturesThrowsLogicExceptionOnCircularDependency(): void
    {
        // RED boundary for report §9.2: a genuine dependency cycle (A needs B,
        // B needs A) must fail loudly with a LogicException, not hang the
        // process via unbounded recursion.
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/circular/i');

        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class), [
            new BasicFeatureStruct(['feature_code' => 'test_cycle_a']),
            new BasicFeatureStruct(['feature_code' => 'test_cycle_b']),
        ]);

        $featureSet->sortFeatures();
    }
}

namespace Plugins\Features;

class TestFeaturesetStubA extends BaseFeature
{
    public const string FEATURE_CODE = 'test_featureset_stub_a';
}

class TestFeatureConflictDeclarer extends BaseFeature
{
    public const string FEATURE_CODE = 'test_feature_conflict_declarer';

    protected static array $conflictingDependencies = ['test_featureset_stub_a'];
}

class TestTopoSortA extends BaseFeature
{
    public const string FEATURE_CODE = 'test_topo_sort_a';

    protected static array $dependencies = ['test_topo_sort_b'];
}

class TestTopoSortB extends BaseFeature
{
    public const string FEATURE_CODE = 'test_topo_sort_b';

    protected static array $dependencies = ['test_topo_sort_c'];
}

class TestTopoSortC extends BaseFeature
{
    public const string FEATURE_CODE = 'test_topo_sort_c';
}

class TestCycleA extends BaseFeature
{
    public const string FEATURE_CODE = 'test_cycle_a';

    protected static array $dependencies = ['test_cycle_b'];
}

class TestCycleB extends BaseFeature
{
    public const string FEATURE_CODE = 'test_cycle_b';

    protected static array $dependencies = ['test_cycle_a'];
}
