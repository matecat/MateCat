<?php

namespace Matecat\Core\Model\ProjectCreation;

use ArrayObject;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Run\ValidateProjectCreationEvent;
use Model\Files\MetadataDao;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Logger\MatecatLogger;
use Utils\TaskRunner\Exceptions\EndQueueException;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::validateBeforeCreation()}.
 *
 * Verifies:
 * - Calls checkForProjectAssignment
 * - Calls features->dispatch(new ValidateProjectCreationEvent(...))
 * - Throws EndQueueException when errors exist after validation
 * - Does not throw when no errors
 */
class ValidateBeforeCreationTest extends AbstractTest
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

        // Set defaults so checkForProjectAssignment doesn't crash
        $this->pm->setProjectStructureValue('uid', null);
        $this->pm->setProjectStructureValue('result', ['errors' => new ArrayObject()]);
        $this->pm->setProjectStructureValue('qa_model', null);
    }

    #[Test]
    public function doesNotThrowWhenNoErrors(): void
    {
        $features = $this->createStub(FeatureSet::class);

        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->setProjectStructureValue('uid', null);
        $this->pm->setProjectStructureValue('result', ['errors' => new ArrayObject()]);
        $this->pm->setProjectStructureValue('qa_model', null);

        // Should not throw
        $this->pm->callValidateBeforeCreation();
        $this->assertTrue(true);
    }

    /**
     * @return array<string, array{int, string, int, string, bool}>
     */
    public static function secondPassReviewCases(): array
    {
        return [
            '101 on, approved2'               => [1, 'APPROVED2', 0, 'TRANSLATED', true],
            '100 on, approved2'               => [0, 'APPROVED', 1, 'APPROVED2', true],
            '101 off, approved2'              => [0, 'APPROVED2', 0, 'TRANSLATED', false],
            '100 off, approved2'              => [1, 'APPROVED', 0, 'APPROVED2', false],
            'both on, no approved2'           => [1, 'APPROVED', 1, 'TRANSLATED', false],
        ];
    }

    #[Test]
    #[DataProvider('secondPassReviewCases')]
    public function raisesSecondPassReviewForApproved2PreTranslations(
        int $pretranslate101,
        string $status101,
        int $pretranslate100,
        string $status100,
        bool $expected
    ): void {
        $this->pm->setProjectStructureValue('pretranslate_101', $pretranslate101);
        $this->pm->setProjectStructureValue('pretranslate_101_status', $status101);
        $this->pm->setProjectStructureValue('pretranslate_100', $pretranslate100);
        $this->pm->setProjectStructureValue('pretranslate_100_status', $status100);

        $this->pm->callValidateBeforeCreation();

        $this->assertSame($expected, $this->pm->getTestProjectStructure()->create_2_pass_review);
    }

    #[Test]
    public function neverLowersSecondPassReviewAlreadyRequested(): void
    {
        $this->pm->setProjectStructureValue('pretranslate_101', 0);
        $this->pm->setProjectStructureValue('pretranslate_100', 0);
        $this->pm->setProjectStructureValue('create_2_pass_review', true);

        $this->pm->callValidateBeforeCreation();

        $this->assertTrue($this->pm->getTestProjectStructure()->create_2_pass_review);
    }

    #[Test]
    public function throwsEndQueueExceptionWhenErrorsExist(): void
    {
        // Set up features to inject an error during validateProjectCreation
        $features = $this->createStub(FeatureSet::class);
        $features->method('dispatch')
            ->willReturnCallback(function (ValidateProjectCreationEvent $event) {
                $event->projectStructure->result['errors'][] = ['code' => -99, 'message' => 'Validation failed'];

                return $event;
            });

        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->setProjectStructureValue('uid', null);
        $this->pm->setProjectStructureValue('result', ['errors' => new ArrayObject()]);
        $this->pm->setProjectStructureValue('qa_model', null);

        $this->expectException(EndQueueException::class);
        $this->expectExceptionMessage('Invalid project found.');

        $this->pm->callValidateBeforeCreation();
    }

    #[Test]
    public function callsCheckForProjectAssignmentWithUid(): void
    {
        $team = new TeamStruct([
            'id'         => 5,
            'name'       => 'Test Team',
            'created_by' => 1,
            'created_at' => '2025-01-01 00:00:00',
            'type'       => 'personal',
        ]);

        $features = $this->createStub(FeatureSet::class);
        

        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->setProjectStructureValue('uid', 42);
        $this->pm->setProjectStructureValue('team', $team);
        $this->pm->setProjectStructureValue('result', ['errors' => new ArrayObject()]);
        $this->pm->setProjectStructureValue('qa_model', null);

        $teamDao = $this->createMock(TeamDao::class);
        $teamDao->expects($this->once())->method('destroyCacheAssigneeWithProjectsByTeam');
        $this->pm->setTeamDao($teamDao);

        $this->pm->callValidateBeforeCreation();

        $ps = $this->pm->getTestProjectStructure();
        $this->assertSame(42, $ps->id_assignee);
    }

    #[Test]
    public function skipsAssignmentWhenUidEmpty(): void
    {
        $features = $this->createStub(FeatureSet::class);
        

        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->setProjectStructureValue('uid', null);
        $this->pm->setProjectStructureValue('result', ['errors' => new ArrayObject()]);
        $this->pm->setProjectStructureValue('qa_model', null);

        $teamDao = $this->createMock(TeamDao::class);
        $teamDao->expects($this->never())->method('destroyCacheAssigneeWithProjectsByTeam');
        $this->pm->setTeamDao($teamDao);

        $this->pm->callValidateBeforeCreation();
        $this->assertTrue(true);
    }
}
