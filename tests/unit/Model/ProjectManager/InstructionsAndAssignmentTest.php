<?php

namespace unit\Model\ProjectManager;

use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Collections\RecursiveArrayObject;
use Utils\Logger\MatecatLogger;

/**
 * Step 11c – Tests for:
 *
 * - _insertInstructions()
 * - __checkForProjectAssignment()
 */
class InstructionsAndAssignmentTest extends AbstractTest
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

    /**
     * Build a fully-populated TeamStruct (all non-nullable properties set).
     */
    private function makeTeam(int $id, string $name = 'Test Team'): TeamStruct
    {
        return new TeamStruct([
            'id'         => $id,
            'name'       => $name,
            'created_by' => 1,
            'created_at' => '2025-01-01 00:00:00',
            'type'       => 'personal',
        ]);
    }

    // ── _insertInstructions() ──────────────────────────────────────

    #[Test]
    public function insertInstructionsPassesValueThroughFeatureFilter(): void
    {
        $features = $this->createMock(FeatureSet::class);
        $features->expects($this->once())
            ->method('filter')
            ->with('decodeInstructions', 'raw instructions')
            ->willReturn('decoded instructions');

        $dao = $this->createMock(MetadataDao::class);
        $dao->expects($this->once())
            ->method('insert')
            ->with(999, 42, 'instructions', 'decoded instructions');

        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $dao,
            $this->createStub(MatecatLogger::class),
        );

        $this->pm->callInsertInstructions(42, 'raw instructions');
    }

    #[Test]
    public function insertInstructionsUsesProjectIdFromStructure(): void
    {
        $features = $this->createStub(FeatureSet::class);
        $features->method('filter')->willReturnArgument(1);

        $dao = $this->createMock(MetadataDao::class);
        $dao->expects($this->once())
            ->method('insert')
            ->with(777, 10, 'instructions', 'some text');

        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $dao,
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->setProjectStructureValue('id_project', 777);

        $this->pm->callInsertInstructions(10, 'some text');
    }

    #[Test]
    public function insertInstructionsFilterReturnsTransformedValue(): void
    {
        $features = $this->createMock(FeatureSet::class);
        $features->method('filter')
            ->with('decodeInstructions', 'base64data')
            ->willReturn('plain text result');

        $dao = $this->createMock(MetadataDao::class);
        $dao->expects($this->once())
            ->method('insert')
            ->with(999, 5, 'instructions', 'plain text result');

        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $dao,
            $this->createStub(MatecatLogger::class),
        );

        $this->pm->callInsertInstructions(5, 'base64data');
    }

    // ── __checkForProjectAssignment() ──────────────────────────────

    #[Test]
    public function checkForProjectAssignmentDoesNothingWhenUidEmpty(): void
    {
        $this->pm->setProjectStructureValue('uid', null);

        $teamDao = $this->createMock(TeamDao::class);
        $teamDao->expects($this->never())->method('destroyCacheAssignee');
        $this->pm->setTeamDao($teamDao);

        $this->pm->callCheckForProjectAssignment();

        $ps = $this->pm->getTestProjectStructure();
        $this->assertNull($ps['id_assignee']);
    }

    #[Test]
    public function checkForProjectAssignmentDoesNothingWhenUidZero(): void
    {
        $this->pm->setProjectStructureValue('uid', 0);

        $teamDao = $this->createMock(TeamDao::class);
        $teamDao->expects($this->never())->method('destroyCacheAssignee');
        $this->pm->setTeamDao($teamDao);

        $this->pm->callCheckForProjectAssignment();
    }

    #[Test]
    public function checkForProjectAssignmentSetsIdAssigneeFromUid(): void
    {
        $team = $this->makeTeam(10);

        $features = $this->createStub(FeatureSet::class);
        $features->method('filter')->willReturnArgument(1);

        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->setProjectStructureValue('uid', 55);
        $this->pm->setProjectStructureValue('team', $team);

        $teamDao = $this->createStub(TeamDao::class);
        $this->pm->setTeamDao($teamDao);

        $this->pm->callCheckForProjectAssignment();

        $ps = $this->pm->getTestProjectStructure();
        $this->assertSame(55, $ps['id_assignee']);
    }

    #[Test]
    public function checkForProjectAssignmentNormalizesTeamThroughFeatureFilter(): void
    {
        $team = $this->makeTeam(7, 'Original');

        // Feature filter modifies the team array
        $features = $this->createMock(FeatureSet::class);
        $features->method('filter')
            ->with('filter_team_for_project_creation', $this->anything())
            ->willReturn(['id' => 7, 'name' => 'Filtered', 'created_by' => 1, 'created_at' => '2025-01-01 00:00:00', 'type' => 'personal']);

        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->setProjectStructureValue('uid', 100);
        $this->pm->setProjectStructureValue('team', $team);

        $teamDao = $this->createStub(TeamDao::class);
        $this->pm->setTeamDao($teamDao);

        $this->pm->callCheckForProjectAssignment();

        $ps = $this->pm->getTestProjectStructure();
        $resultTeam = $ps['team'];
        $this->assertInstanceOf(TeamStruct::class, $resultTeam);
        $this->assertSame('Filtered', $resultTeam->name);
        $this->assertSame(7, $resultTeam->id);
    }

    #[Test]
    public function checkForProjectAssignmentCallsDestroyCacheAssignee(): void
    {
        $team = $this->makeTeam(3, 'Cache Team');

        $features = $this->createStub(FeatureSet::class);
        $features->method('filter')->willReturnArgument(1);

        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->setProjectStructureValue('uid', 88);
        $this->pm->setProjectStructureValue('team', $team);

        $teamDao = $this->createMock(TeamDao::class);
        $teamDao->expects($this->once())
            ->method('destroyCacheAssignee')
            ->with($this->callback(function (TeamStruct $t) {
                return $t->id === 3;
            }));

        $this->pm->setTeamDao($teamDao);
        $this->pm->callCheckForProjectAssignment();
    }

    #[Test]
    public function checkForProjectAssignmentUsesRecursiveArrayObjectTeam(): void
    {
        // Team stored as RecursiveArrayObject (as it would come from project creation)
        $teamRAO = new RecursiveArrayObject([
            'id'         => 15,
            'name'       => 'RAO Team',
            'created_by' => 2,
            'created_at' => '2025-06-15 12:00:00',
            'type'       => 'general',
        ]);

        $features = $this->createStub(FeatureSet::class);
        $features->method('filter')->willReturnArgument(1);

        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $this->pm->setProjectStructureValue('uid', 33);
        $this->pm->setProjectStructureValue('team', $teamRAO);

        $teamDao = $this->createStub(TeamDao::class);
        $this->pm->setTeamDao($teamDao);

        $this->pm->callCheckForProjectAssignment();

        $ps = $this->pm->getTestProjectStructure();
        $resultTeam = $ps['team'];
        $this->assertInstanceOf(TeamStruct::class, $resultTeam);
        $this->assertSame(15, $resultTeam->id);
        $this->assertSame('RAO Team', $resultTeam->name);
    }
}
