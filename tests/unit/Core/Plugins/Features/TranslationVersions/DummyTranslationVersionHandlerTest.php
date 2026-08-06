<?php

namespace Matecat\Core\Plugins\Features\TranslationVersions;

use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Propagation\PropagationResult;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\TranslationVersions\Handlers\DummyTranslationVersionHandler;
use Plugins\Features\TranslationVersions\StoreTranslationEventParams;

/**
 * The null implementation used when the TranslationVersions feature is not enabled on a project.
 *
 * It is worth testing precisely because it does nothing: every one of its three methods is a
 * promise about what a caller may assume when versioning is off. `propagateTranslation()` in
 * particular must return the empty `PropagationResult` and not an empty array or null, because
 * `SetTranslationController` reads `propagatedIds` off whatever comes back without checking first.
 */
class DummyTranslationVersionHandlerTest extends AbstractTest
{
    private DummyTranslationVersionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new DummyTranslationVersionHandler();
    }

    /**
     * Versioning off means no version is ever recorded, so the increment never happens and the
     * caller is told so. Returning true here would make `SetTranslationController` persist a
     * version number no row backs.
     */
    #[Test]
    public function saveVersionAndIncrementNeverSavesAVersion(): void
    {
        $newTranslation = new SegmentTranslationStruct();
        $newTranslation->id_segment = 42;
        $newTranslation->version_number = 3;

        $oldTranslation = new SegmentTranslationStruct();
        $oldTranslation->id_segment = 42;
        $oldTranslation->version_number = 3;

        $this->assertFalse($this->handler->saveVersionAndIncrement($newTranslation, $oldTranslation));

        // The structs are left alone: the real handler is what bumps the number, and a caller that
        // reads the version after this call must see what it passed in.
        $this->assertSame(3, $newTranslation->version_number);
        $this->assertSame(3, $oldTranslation->version_number);
    }

    /**
     * Accepts the same DTO the real handler does and does nothing with it. The value under test is
     * that it does not throw: the controllers call this unconditionally, without knowing which
     * handler the feature set gave them.
     */
    #[Test]
    public function storeTranslationEventDiscardsTheEventWithoutThrowing(): void
    {
        $this->handler->storeTranslationEvent($this->makeParams());

        $this->assertTrue(true, 'storing an event on a project without versioning is a no-op');
    }

    /**
     * The typed contract that replaced an untyped empty array. `propagatedIds` and
     * `segmentsForPropagation` are lists on both handlers, so the consumers never branch on which
     * one answered.
     */
    #[Test]
    public function propagateTranslationReturnsTheEmptyResultRatherThanAnArrayOrNull(): void
    {
        $translation = new SegmentTranslationStruct();
        $translation->id_segment = 42;

        $result = $this->handler->propagateTranslation($translation);

        $this->assertInstanceOf(PropagationResult::class, $result);
        $this->assertSame([], $result->totals);
        $this->assertSame([], $result->propagatedIds);
        $this->assertSame([], $result->segmentsForPropagation);
    }

    private function makeParams(): StoreTranslationEventParams
    {
        $translation = new SegmentTranslationStruct();
        $translation->id_segment = 42;

        $oldTranslation = new SegmentTranslationStruct();
        $oldTranslation->id_segment = 42;

        $chunk = new JobStruct();
        $chunk->id = 9001;

        $user = new UserStruct();
        $user->uid = 7;

        $project = new ProjectStruct();
        $project->id = 555;

        return new StoreTranslationEventParams(
            $translation,
            $oldTranslation,
            PropagationResult::empty(),
            $chunk,
            $user,
            2,
            $this->createStub(FeatureSet::class),
            $project
        );
    }
}
