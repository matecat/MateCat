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
use Model\Projects\ProjectStruct;
use Model\Teams\TeamStruct;
use Utils\Registry\AppConfig;
use View\API\V2\Json\Project;

class TeamsProjectsController extends KleinController
{

    /** @var TeamStruct */
    protected TeamStruct $team;

    protected function afterConstruct(): void
    {
        parent::afterConstruct();
        $this->appendValidator(new LoginValidator($this));
        $this->appendValidator(new TeamAccessValidator($this));
    }

    /**
     * @throws NotFoundException
     * @throws \Model\Exceptions\NotFoundException
     * @throws Exception
     */
    public function getPaginated(): void
    {
        $id_team = $this->request->param('id_team');
        $page    = $this->request->param('page') ? $this->request->param('page') : 1;
        $step    = $this->request->param('step') ? ($this->request->param('step') <= 50 ? $this->request->param('step') : 50) : 20;
        $search  = $this->request->param('search');

        $filter = [
                'limit'  => (int)$step,
                'offset' => $this->getOffset($page, $step),
        ];

        if ($search) {
            $filter[ 'search' ] = $search;
        }

        $this->featureSet->loadFromUserEmail($this->user->email);

        /** @var ProjectStruct[] $projectsList */
        $projectsList = ProjectDao::findByTeamId($id_team, $filter);
        $projectsList = (new Project($projectsList))->render();

        $totals      = ProjectDao::getTotalCountByTeamId($id_team, $filter, 60 * 5);
        $total_pages = $this->getTotalPages($step, $totals);

        if ($totals == 0) {
            $this->response->status()->setCode(204);
            $this->response->json([
                    '_links'   => $this->_getPaginationLinks($page, $totals, $step, $search),
                    'projects' => []
            ]);
            exit();
        }

        if ($page > $total_pages) {
            throw new NotFoundException($page . " too high, maximum value is " . $total_pages, 404);
        }

        $this->response->json([
                '_links'   => $this->_getPaginationLinks($page, $totals, $step, $search),
                'projects' => $projectsList
        ]);
    }

    /**
     * @param int    $page
     * @param int    $totals
     * @param int    $step
     * @param ?array $search
     *
     * @return array
     */
    private function _getPaginationLinks(int $page, int $totals, int $step = 20, ?array $search = []): array
    {
        $url = parse_url($_SERVER[ 'REQUEST_URI' ]);

        $links = [
                "base"        => AppConfig::$HTTPHOST,
                "self"        => $_SERVER[ 'REQUEST_URI' ],
                "page"        => $page,
                "step"        => $step,
                "totals"      => $totals,
                "total_pages" => $total_pages = $this->getTotalPages($step, $totals),
        ];

        $last_part_of_url = ($step != 20 ? "&step=" . $step : null) . (isset($search[ 'name' ]) ? "&search[name]=" . $search[ 'name' ] : null) . (
                isset($search[ 'id' ]) ? "&search[id]=" . $search[ 'id' ] : null);

        if ($page < $total_pages) {
            $links[ 'next' ] = $url[ 'path' ] . "?page=" . ($page + 1) . $last_part_of_url;
        }

        if ($page > 1) {
            $links[ 'prev' ] = $url[ 'path' ] . "?page=" . ($page - 1) . $last_part_of_url;
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
     */
    private function getTotalPages(int $step, int $totals): int
    {
        return (int)ceil($totals / $step);
    }

    /**
     * @param $team
     */
    public function setTeam($team): void
    {
        $this->team = $team;
    }
}