<?php

namespace Matecat\Core\Model\ProjectCreation;

use ArrayObject;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\ProjectCreation\ProjectStructure;
use Model\ProjectCreation\TmKeyService;
use PHPUnit\Framework\Attributes\Test;
use Utils\Logger\MatecatLogger;
use Utils\TaskRunner\Exceptions\EndQueueException;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::setPrivateTmKeysOrFail()}.
 *
 * The method is a thin gate around TmKeyService: it only runs when private TM keys were requested,
 * and it turns errors the service recorded on the project structure into an EndQueueException so
 * the queue stops instead of creating a half-configured project.
 *
 * TmKeyService is seeded directly (getTmKeyService() resolves it with `??=`), which keeps the real
 * service — and its TMX service wrapper and HTTP calls to MyMemory — out of the test.
 */
class SetPrivateTmKeysOrFailTest extends AbstractTest
{
    private TestableProjectManager $pm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->setProjectStructureValue('result', ['errors' => new ArrayObject()]);
        $this->pm->setProjectStructureValue('private_tm_key', []);
    }

    /**
     * @param callable(ProjectStructure, ?string):void|null $onSetKeys
     */
    private function seedTmKeyService(?callable $onSetKeys = null): void
    {
        $service = $this->createStub(TmKeyService::class);

        if ($onSetKeys !== null) {
            $service->method('setPrivateTMKeys')->willReturnCallback($onSetKeys);
        }

        $this->pm->setTmKeyService($service);
    }

    #[Test]
    public function doesNothingWhenNoPrivateTmKeyWasRequested(): void
    {
        // A stub with no configured call: reaching setPrivateTMKeys() at all would be the bug
        $service = $this->createStub(TmKeyService::class);
        $service->method('setPrivateTMKeys')->willReturnCallback(
            fn() => $this->fail('setPrivateTMKeys() must not be called without private TM keys')
        );
        $this->pm->setTmKeyService($service);

        $this->pm->callSetPrivateTmKeysOrFail('');

        $this->assertCount(0, $this->pm->getTestProjectStructure()->result['errors']);
    }

    #[Test]
    public function delegatesToTheTmKeyServiceAndPassesTheTmxFileName(): void
    {
        $this->pm->setProjectStructureValue('private_tm_key', [['key' => 'a-private-key']]);

        $seen = null;
        $this->seedTmKeyService(function (ProjectStructure $structure, ?string $tmxFileName) use (&$seen): void {
            $seen = $tmxFileName;
        });

        $this->pm->callSetPrivateTmKeysOrFail('glossary.tmx');

        $this->assertSame('glossary.tmx', $seen);
    }

    #[Test]
    public function throwsEndQueueExceptionWhenTheServiceRecordedErrors(): void
    {
        $this->pm->setProjectStructureValue('private_tm_key', [['key' => 'a-private-key']]);

        $this->seedTmKeyService(function (ProjectStructure $structure, ?string $tmxFileName): void {
            $structure->result['errors'][] = ['code' => -1, 'message' => 'The key is invalid'];
        });

        $this->expectException(EndQueueException::class);
        $this->expectExceptionMessage('Invalid project found.');

        $this->pm->callSetPrivateTmKeysOrFail('');
    }

    #[Test]
    public function doesNotThrowWhenTheServiceRecordedNoErrors(): void
    {
        $this->pm->setProjectStructureValue('private_tm_key', [['key' => 'a-private-key']]);

        $this->seedTmKeyService(static function (ProjectStructure $structure, ?string $tmxFileName): void {
            // a clean run records nothing
        });

        $this->pm->callSetPrivateTmKeysOrFail('');

        $this->assertCount(0, $this->pm->getTestProjectStructure()->result['errors']);
    }
}
