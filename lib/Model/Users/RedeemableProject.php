<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 25/11/2016
 * Time: 14:33
 */

namespace Model\Users;

use Exception;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Teams\TeamDao;
use ReflectionException;
use RuntimeException;
use Utils\Session\SessionStore;
use Utils\Url\CanonicalRoutes;

class RedeemableProject
{
    protected UserStruct $user;

    protected SessionStore $session;

    protected ?ProjectStruct $project = null;

    private ProjectDao $projectDao;
    private JobDao $jobDao;
    private TeamDao $teamDao;

    public function __construct(
        UserStruct $user,
        SessionStore $session,
        TeamDao $teamDao
    ) {
        $this->user = $user;
        $this->session = $session;
        $this->teamDao = $teamDao;
        $this->projectDao = new ProjectDao($teamDao->getDatabaseHandler());
        $this->jobDao = new JobDao($teamDao->getDatabaseHandler());
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function isPresent(): bool
    {
        return $this->__getProject() != null;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function __getProject(): ?ProjectStruct
    {
        if (!isset($this->project)) {
            if ($this->session->has('last_created_pid')) {
                $this->project = $this->projectDao->findById($this->session->get('last_created_pid'));
            }
        }

        return $this->project;
    }

    public function isRedeemable(): bool
    {
        return $this->session->get('redeem_project') === true;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function redeem(): void
    {
        if ($this->isPresent() && $this->isRedeemable()) {
            $project = $this->project ?? throw new RuntimeException('Project must be set after isPresent() check');
            $project->id_customer = $this->user->getEmail() ?? throw new \RuntimeException('User email must be set for project redemption');
            $project->id_team = $this->user->getPersonalTeam($this->teamDao)->id;
            $project->id_assignee = $this->user->getUid();

            $this->projectDao->updateStruct($project, [
                'fields' => ['id_team', 'id_customer', 'id_assignee']
            ]);

            $this->jobDao->updateOwner($project, $this->user);
        }

        $this->clear();
    }

    public function clear(): void
    {
        $this->session->remove('redeem_project');
        $this->session->remove('last_created_pid');
    }

    public function getProject(): ?ProjectStruct
    {
        return $this->project;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function tryToRedeem(): void
    {
        if ($this->isPresent() && $this->isRedeemable()) {
            $this->redeem();
        }
    }

    /**
     * @throws Exception
     */
    public function getDestinationURL(): ?string
    {
        if ($this->isPresent() && $this->project !== null) {
            return CanonicalRoutes::analyze([
                'project_name' => $this->project->name,
                'id_project' => $this->project->id,
                'password' => $this->project->password
            ]);
        }

        return null;
    }

}