<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/02/2017
 * Time: 10:06
 */

namespace Controller\API\V3;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\TeamAccessValidator;
use Exception;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectsCount;
use Model\Projects\ProjectStruct;
use Model\Teams\TeamStruct;
use Utils\Registry\AppConfig;
use View\API\V2\Json\Project;

class TeamsProjectsController extends KleinController
{

    /** @var TeamStruct */
    protected TeamStruct $team;

    private ?ProjectDao $projectDao = null;

    private function getProjectDao(): ProjectDao
    {
        return $this->projectDao ??= new ProjectDao($this->getDatabase());
    }

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $this->appendValidator(new TeamAccessValidator($this));
    }

    /**
     * @throws NotFoundException
     * @throws \Model\Exceptions\NotFoundException
     * @throws Exception
     * @throws \TypeError
     * @throws \DivisionByZeroError
     */
    public function getPaginated(): void
    {
        $id_team = $this->request->param('id_team');
        $page = $this->request->param('page') ? $this->request->param('page') : 1;
        $step = $this->request->param('step') ? ($this->request->param('step') <= 50 ? $this->request->param('step') : 50) : 20;
        $search = $this->request->param('search');

        $filter = [
            // one row beyond the page: it tells us a next page exists without asking the total,
            // which stops counting at a ceiling and so cannot name the last page of a large team
            'limit' => (int)$step + 1,
            'offset' => $this->getOffset($page, $step),
        ];

        if ($search) {
            $filter['search'] = $search;
        }

        $this->featureSet->loadFromUserEmail($this->user->email ?? '');

        $totals = $this->getProjectDao()->getTotalCountByTeamId($id_team, $filter, 60 * 5);

        // an exact total still names the last page up front, and refusing here spares an
        // out-of-range request the deep OFFSET scan it would otherwise pay for
        $total_pages = $this->getTotalPages($step, $totals->value);
        if (!$totals->approximated && $totals->value > 0 && $page > $total_pages) {
            throw new NotFoundException($page . " too high, maximum value is " . $total_pages, 404);
        }

        /** @var ProjectStruct[] $rows */
        $rows = $this->getProjectDao()->findByTeamId($id_team, $filter);
        $hasNextPage = count($rows) > $step;
        $rows = array_slice($rows, 0, (int)$step);

        if (empty($rows)) {
            // a team with no projects answers 204 whatever page was asked for, as it always has;
            // only a team that does hold projects can be asked for a page past its end
            if ($page > 1 && $totals->value > 0) {
                throw new NotFoundException($page . " is past the last page", 404);
            }

            $this->response->status()->setCode(204);
            $this->response->json([
                '_links' => $this->_getPaginationLinks($page, $totals, false, $step, $search),
                'projects' => []
            ]);
            return;
        }

        $this->response->json([
            '_links' => $this->_getPaginationLinks($page, $totals, $hasNextPage, $step, $search),
            'projects' => (new Project($this->getDatabase(), $rows))->render()
        ]);
    }

    /**
     * `totals` stops at a ceiling, so once `totals_approximated` is true it is a lower bound and
     * `total_pages` counts only the pages that bound covers. `next` therefore comes from having
     * fetched one row past the page rather than from comparing against `total_pages`, which would
     * hide every page beyond the cap.
     *
     * @param int $page
     * @param ProjectsCount $totals
     * @param bool $hasNextPage
     * @param int $step
     * @param array<string, mixed>|null $search
     *
     * @return array<string, mixed>
     * @throws \DivisionByZeroError
     */
    private function _getPaginationLinks(int $page, ProjectsCount $totals, bool $hasNextPage, int $step = 20, ?array $search = []): array
    {
        $url = parse_url($_SERVER['REQUEST_URI']);
        $urlPath = is_array($url) ? ($url['path'] ?? '') : '';

        $links = [
            "base" => AppConfig::$HTTPHOST,
            "self" => $_SERVER['REQUEST_URI'],
            "page" => $page,
            "step" => $step,
            "totals" => $totals->value,
            "totals_approximated" => $totals->approximated,
            "total_pages" => $this->getTotalPages($step, $totals->value),
        ];

        $last_part_of_url = ($step != 20 ? "&step=" . $step : null) . (isset($search['name']) ? "&search[name]=" . $search['name'] : null) . (
            isset($search['id']) ? "&search[id]=" . $search['id'] : null);

        if ($hasNextPage) {
            $links['next'] = $urlPath . "?page=" . ($page + 1) . $last_part_of_url;
        }

        if ($page > 1) {
            $links['prev'] = $urlPath . "?page=" . ($page - 1) . $last_part_of_url;
        }

        return $links;
    }

    /**
     * @param int $page
     * @param int $step
     *
     * @return int
     */
    private function getOffset(int $page, int $step): int
    {
        if ($page === 1) {
            return 0;
        }

        return $step * ($page - 1);
    }

    /**
     * @param int $step
     * @param int $totals
     *
     * @return int
     * @throws \DivisionByZeroError
     */
    private function getTotalPages(int $step, int $totals): int
    {
        return (int)ceil($totals / $step);
    }

    public function setTeam(TeamStruct $team): void
    {
        $this->team = $team;
    }
}