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
use Plugins\Features\TranslationVersions\StoreTranslationEventParams;
use ReflectionClass;

/**
 * The typed replacement for `storeTranslationEvent(array $params)`.
 *
 * The old signature was an eight-key array documented only in a docblock, which meant the
 * implementation had to re-check at runtime what the caller was supposed to have provided —
 * `TranslationVersionsHandler` threw `storeTranslationEvent requires the acting user in
 * $params['user']` for a key the docblock already declared mandatory. A constructor with eight
 * required, non-nullable parameters moves that check to the call site, where the compiler makes it.
 */
class StoreTranslationEventParamsTest extends AbstractTest
{
    private function make(): StoreTranslationEventParams
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

    #[Test]
    public function itCarriesEveryValueItWasBuiltWith(): void
    {
        $params = $this->make();

        $this->assertSame(42, $params->translation->id_segment);
        $this->assertSame(42, $params->oldTranslation->id_segment);
        $this->assertSame([], $params->propagation->propagatedIds);
        $this->assertSame(9001, $params->chunk->id);
        $this->assertSame(7, $params->user->uid);
        $this->assertSame(2, $params->sourcePageCode);
        $this->assertInstanceOf(FeatureSet::class, $params->features);
        $this->assertSame(555, $params->project->id);
    }

    /**
     * The invariant that lets the handler's runtime guard go away. If any parameter ever becomes
     * optional or nullable, a caller can once again omit it and the handler will need to re-check —
     * so this failing is the signal to reinstate the guard rather than to relax the assertion.
     */
    #[Test]
    public function everyConstructorParameterIsRequiredAndNonNullable(): void
    {
        $constructor = (new ReflectionClass(StoreTranslationEventParams::class))->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(9, $constructor->getNumberOfParameters());
        $this->assertSame(8, $constructor->getNumberOfRequiredParameters());

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            $this->assertNotNull($type, "\${$parameter->getName()} must be typed");
            $this->assertFalse($type->allowsNull(), "\${$parameter->getName()} must not accept null");
        }
    }

    /**
     * `propagation` is the one field a caller is tempted to leave out, because two of the three
     * production paths never propagate. It is non-nullable on purpose: `Translated.php:479` indexes
     * the propagated ids unguarded on the way to a Kafka payload, so the absence of propagation has
     * to be an empty result rather than null.
     */
    #[Test]
    public function propagationIsRepresentedByTheEmptyResultRatherThanNull(): void
    {
        $params = $this->make();

        $this->assertInstanceOf(PropagationResult::class, $params->propagation);
        $this->assertSame([], $params->propagation->propagatedIds);
        $this->assertSame([], $params->propagation->segmentsForPropagation);
    }
}
