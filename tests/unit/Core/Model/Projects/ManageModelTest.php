<?php

namespace Matecat\Core\Model\Projects;

use DateInterval;
use DateTime;
use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Projects\ManageModel;
use Model\Projects\ProjectsCount;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ManageModel::class)]
class ManageModelTest extends AbstractTest
{
    #[Test]
    public function formatJobDateForTodayReturnsTodayPrefix(): void
    {
        $date = (new DateTime())->setTime(10, 15, 0);

        $actual = ManageModel::formatJobDate($date->format('Y-m-d H:i:s'));

        $this->assertSame('Today, 10:15', $actual);
    }

    #[Test]
    public function formatJobDateForYesterdayReturnsYesterdayPrefix(): void
    {
        $date = (new DateTime('yesterday'))->setTime(8, 20, 0);

        $actual = ManageModel::formatJobDate($date->format('Y-m-d H:i:s'));

        $this->assertSame('Yesterday, 08:20', $actual);
    }

    #[Test]
    public function formatJobDateForCurrentMonthReturnsMonthDayTime(): void
    {
        $date = (new DateTime('now'))->sub(new DateInterval('P2D'))->setTime(9, 25, 0);
        if ($date->format('Y-m') !== (new DateTime('now'))->format('Y-m')) {
            $date = new DateTime('first day of this month 09:25:00');
        }

        $actual = ManageModel::formatJobDate($date->format('Y-m-d H:i:s'));

        // If the date happens to be today or yesterday, those branches take priority
        $now = new DateTime();
        $yesterday = (clone $now)->sub(new DateInterval('P1D'));
        if ($date->format('Y-m-d') === $now->format('Y-m-d')) {
            $this->assertSame('Today, ' . $date->format('H:i'), $actual);
        } elseif ($date->format('Y-m-d') === $yesterday->format('Y-m-d')) {
            $this->assertSame('Yesterday, ' . $date->format('H:i'), $actual);
        } else {
            $this->assertSame($date->format('M d, H:i'), $actual);
        }
    }

    #[Test]
    public function formatJobDateForCurrentYearDifferentMonthReturnsMonthDayTime(): void
    {
        $currentYear = (new DateTime())->format('Y');
        $currentMonth = (new DateTime())->format('m');
        $candidateMonth = $currentMonth === '01' ? '02' : '01';
        $date = new DateTime(sprintf('%s-%s-15 12:40:00', $currentYear, $candidateMonth));

        $actual = ManageModel::formatJobDate($date->format('Y-m-d H:i:s'));

        $this->assertSame($date->format('M d, H:i'), $actual);
    }

    #[Test]
    public function formatJobDateForDifferentYearReturnsYearMonthDayTime(): void
    {
        $date = (new DateTime('now'))->sub(new DateInterval('P400D'))->setTime(14, 50, 0);

        $actual = ManageModel::formatJobDate($date->format('Y-m-d H:i:s'));

        $this->assertSame($date->format('Y M d H:i'), $actual);
    }

    #[Test]
    public function formatJobDateWithNullDefaultsToToday(): void
    {
        $actual = ManageModel::formatJobDate(null);

        $this->assertMatchesRegularExpression('/^Today, \d{2}:\d{2}$/', $actual);
    }

    #[Test]
    public function conditionsForProjectsQueryWithNoFiltersReturnsEmptyArrays(): void
    {
        [$conditions, $data] = TestableManageModel::exposeConditionsForProjectsQuery(null, null, null, null, false);

        $this->assertSame([], $conditions);
        $this->assertSame([], $data);
    }

    #[Test]
    public function conditionsForProjectsQueryWithAllFiltersBuildsExpectedConditionsAndData(): void
    {
        [$conditions, $data] = TestableManageModel::exposeConditionsForProjectsQuery('client', 'en-US', 'it-IT', 'ACTIVE', true);

        $this->assertSame(
            [
                ' p.name LIKE :project_name ',
                ' j.source = :source ',
                ' j.target = :target  ',
                ' j.status_owner = :owner_status ',
                ' j.completed = 1 ',
            ],
            $conditions
        );
        $this->assertSame(
            [
                'project_name' => '%client%',
                'source' => 'en-US',
                'target' => 'it-IT',
                'owner_status' => 'ACTIVE',
            ],
            $data
        );
    }

    #[Test]
    public function getProjectsNumberReturnsACount(): void
    {
        $count = ManageModel::getProjectsNumber(obtainTestDatabase(), null, null, null, null, false);

        $this->assertGreaterThanOrEqual(0, $count->value);
        $this->assertLessThanOrEqual(ProjectsCount::DEFAULT_CAP, $count->value);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function getProjectsNumberWithTeamAndAssigneeFiltersReturnsACount(): void
    {
        $team = new TeamStruct();
        $team->id = 1;

        $assignee = new UserStruct();
        $assignee->uid = 1;

        $count = ManageModel::getProjectsNumber(obtainTestDatabase(), 'a', 'en', 'it', 'NEW', true, $team, $assignee, false);

        $this->assertGreaterThanOrEqual(0, $count->value);
        $this->assertFalse($count->approximated);
    }

    #[Test]
    public function getProjectsViaProtectedMethodReturnsListOfIntegers(): void
    {
        $team = new TeamStruct();
        $team->id = 1;

        $assignee = new UserStruct();
        $assignee->uid = 1;

        $ids = TestableManageModel::exposeGetProjects(
            0,
            5,
            null,
            null,
            null,
            null,
            false,
            null,
            $team,
            $assignee,
            false
        );

        $this->assertIsArray($ids);
        foreach ($ids as $id) {
            $this->assertIsInt($id);
        }
    }

    #[Test]
    public function getProjectsViaProtectedMethodWithoutFiltersReturnsAtLeastOneIntegerId(): void
    {
        $ids = TestableManageModel::exposeGetProjects(
            0,
            1,
            null,
            null,
            null,
            null,
            false,
            null,
            null,
            null,
            false
        );

        $this->assertNotEmpty($ids);
        $this->assertIsInt($ids[0]);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function getProjectsViaProtectedMethodWithNoAssigneeFilterReturnsListOfIntegers(): void
    {
        $team = new TeamStruct();
        $team->id = 1;

        $ids = TestableManageModel::exposeGetProjects(
            0,
            5,
            'a',
            'en',
            'it',
            'NEW',
            true,
            null,
            $team,
            null,
            true
        );

        $this->assertIsArray($ids);
        foreach ($ids as $id) {
            $this->assertIsInt($id);
        }
    }

    #[Test]
    public function getProjectsViaProtectedMethodWithProjectIdFilterReturnsArrayShape(): void
    {
        $ids = TestableManageModel::exposeGetProjects(
            0,
            1,
            null,
            null,
            null,
            null,
            false,
            PHP_INT_MAX,
            null,
            null,
            false
        );

        $this->assertSame([], $ids);
    }

    #[Test]
    public function getProjectsPublicMethodReturnsArrayWhenNoRowsAreRequested(): void
    {
        $user = new UserStruct();

        $result = ManageModel::getProjects(
            $user,
            obtainTestDatabase(),
            0,
            0,
            null,
            null,
            null,
            null,
            false,
            null,
            null,
            null,
            false
        );

        $this->assertSame([], $result);
    }

    #[Test]
    public function getProjectsNumberWithNoAssigneeFilterReturnsACount(): void
    {
        $count = ManageModel::getProjectsNumber(obtainTestDatabase(), null, null, null, null, false, null, null, true);

        $this->assertGreaterThanOrEqual(0, $count->value);
        $this->assertFalse($count->approximated);
    }

    /**
     * The whole point of the cap is that the query stops early, so prove it against the database
     * rather than against the value object: with a cap of one, a fixture set holding more than one
     * project still comes back as one, flagged.
     */
    #[Test]
    public function getProjectsNumberStopsAtTheCap(): void
    {
        $database = obtainTestDatabase();

        $exact = ManageModel::getProjectsNumber($database, null, null, null, null, false);
        $this->assertGreaterThan(1, $exact->value, 'the fixture set is too small to reach a cap of one');
        $this->assertFalse($exact->approximated);

        $capped = ManageModel::getProjectsNumber($database, null, null, null, null, false, null, null, false, 1);

        $this->assertSame(1, $capped->value);
        $this->assertTrue($capped->approximated);
        $this->assertSame('1+', $capped->toString());
    }
}

class TestableManageModel extends ManageModel
{
    public static function exposeConditionsForProjectsQuery(
        ?string $search_in_pname,
        ?string $search_source,
        ?string $search_target,
        ?string $search_status,
        ?bool $search_only_completed = false
    ): array {
        return parent::conditionsForProjectsQuery(
            $search_in_pname,
            $search_source,
            $search_target,
            $search_status,
            $search_only_completed
        );
    }

    public static function exposeGetProjects(
        int $start,
        int $step,
        ?string $search_in_pname,
        ?string $search_source,
        ?string $search_target,
        ?string $search_status,
        ?bool $search_only_completed,
        ?int $project_id,
        ?TeamStruct $team = null,
        ?UserStruct $assignee = null,
        ?bool $no_assignee = false
    ): array {
        return parent::_getProjects(
            obtainTestDatabase(),
            $start,
            $step,
            $search_in_pname,
            $search_source,
            $search_target,
            $search_status,
            $search_only_completed,
            $project_id,
            $team,
            $assignee,
            $no_assignee
        );
    }
}
