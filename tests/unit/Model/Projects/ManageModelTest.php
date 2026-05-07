<?php

namespace unit\Model\Projects;

use DateInterval;
use DateTime;
use Exception;
use Model\Projects\ManageModel;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

#[CoversClass(ManageModel::class)]
class ManageModelTest extends AbstractTest
{
    #[Test]
    public function formatJobDateForTodayReturnsMonthDayTimeBecauseNowIsMutated(): void
    {
        $date = (new DateTime())->setTime(10, 15, 0);

        $actual = ManageModel::formatJobDate($date->format('Y-m-d H:i:s'));

        $this->assertSame($date->format('M d, H:i'), $actual);
    }

    #[Test]
    public function formatJobDateForYesterdayReturnsTodayPrefixWithTime(): void
    {
        $date = (new DateTime('yesterday'))->setTime(8, 20, 0);

        $actual = ManageModel::formatJobDate($date->format('Y-m-d H:i:s'));

        $this->assertSame('Today, 08:20', $actual);
    }

    #[Test]
    public function formatJobDateForCurrentMonthReturnsMonthDayTime(): void
    {
        $date = (new DateTime('now'))->sub(new DateInterval('P2D'))->setTime(9, 25, 0);
        if ($date->format('Y-m') !== (new DateTime('now'))->format('Y-m')) {
            $date = new DateTime('first day of this month 09:25:00');
        }

        $actual = ManageModel::formatJobDate($date->format('Y-m-d H:i:s'));

        $this->assertSame($date->format('M d, H:i'), $actual);
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
    public function formatJobDateWithNullDefaultsToNowMonthDayTime(): void
    {
        $actual = ManageModel::formatJobDate(null);

        $this->assertMatchesRegularExpression('/^[A-Z][a-z]{2} \d{2}, \d{2}:\d{2}$/', $actual);
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
    public function getProjectsNumberReturnsCountRowsShape(): void
    {
        $rows = ManageModel::getProjectsNumber(null, null, null, null, false);

        $this->assertIsArray($rows);
        if ($rows !== []) {
            $this->assertArrayHasKey('c', $rows[0]);
        }
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function getProjectsNumberWithTeamAndAssigneeFiltersReturnsCountRowsShape(): void
    {
        $team = new TeamStruct();
        $team->id = 1;

        $assignee = new UserStruct();
        $assignee->uid = 1;

        $rows = ManageModel::getProjectsNumber('a', 'en', 'it', 'NEW', true, $team, $assignee, false);

        $this->assertIsArray($rows);
        if ($rows !== []) {
            $this->assertArrayHasKey('c', $rows[0]);
        }
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
    public function getProjectsNumberWithNoAssigneeFilterReturnsCountRowsShape(): void
    {
        $rows = ManageModel::getProjectsNumber(null, null, null, null, false, null, null, true);

        $this->assertIsArray($rows);
        if ($rows !== []) {
            $this->assertArrayHasKey('c', $rows[0]);
        }
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
