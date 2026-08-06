<?php

namespace Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Exception;
use Model\Projects\ProjectStruct;
use Model\Teams\MembershipDao;
use Model\Users\UserStruct;
use ReflectionException;

class ProjectAccessValidator extends Base
{

    /**
     * @var ProjectStruct
     */
    private ProjectStruct $project;
    private UserStruct $user;
    private int $ttl;

    /**
     * Class constructor.
     *
     * The acting user is passed rather than read back off the controller, so that whoever authorizes an
     * action states who it is being authorized for. Login state stays a property of the request and is
     * still read from the controller.
     *
     * @param KleinController $controller The KleinController object.
     * @param ProjectStruct $project The ProjectStruct object.
     * @param UserStruct $user The user whose standing over the project is being decided.
     * @param int $ttl
     */
    public function __construct(KleinController $controller, ProjectStruct $project, UserStruct $user, int $ttl = 60 * 10)
    {
        parent::__construct($controller);
        $this->project = $project;
        $this->user = $user;
        $this->ttl = $ttl;
    }


    /**
     * Validates the user's access to the project: its owner, or a member of the team it sits in.
     *
     * This function performs a sequence of steps to verify the user's access:
     * - It checks if the user is logged-in. If not, an AuthorizationError is thrown.
     * - It lets the project's own owner through without a membership lookup (see below).
     * - It tries to find the team associated with the project and the current user.
     *   If no such team exists, an AuthorizationError is thrown.
     * - If a 'setTeam' method exists on the controller, the found team is set on the controller.
     *
     * The owner is allowed explicitly because a project outlives its owner's membership of the team it
     * sits in: ProjectDao moves projects between teams in bulk, and id_team is nullable, so "owner
     * outside the project's team" and "project with no team at all" are both states the application
     * itself produces. Membership alone would take those projects away from the person who created
     * them, which would be a regression rather than a tightening.
     *
     * The comparison is strict and only made for a non-empty owner: a project created by an
     * unauthenticated caller carries id_customer = '' (CreateProjectController), and an empty owner must
     * match nobody — least of all a caller whose own address is empty. The ?? guards read a struct that
     * was built by hand rather than by the DAO, where a typed property may never have been assigned.
     *
     * @return void
     * @throws AuthorizationError If a user is not logged-in or if the user does not belong to the team.
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _validate(): void
    {
        if (!$this->controller->isLoggedIn()) {
            throw new AuthorizationError("Not Authorized. You must be logged in.", 401);
        }

        $ownerEmail = $this->project->id_customer ?? '';
        if ($ownerEmail !== '' && $ownerEmail === ($this->user->email ?? '')) {
            return;
        }

        $idTeam = $this->project->id_team ?? null;
        if ($idTeam === null) {
            throw new AuthorizationError("Not Authorized, the user does not belong to team", 401);
        }

        $team = (new MembershipDao($this->controller->getDatabase()))->setCacheTTL($this->ttl)->findTeamByIdAndUser(
            $idTeam,
            $this->user
        );

        if (empty($team)) {
            throw new AuthorizationError("Not Authorized, the user does not belong to team " . $idTeam, 401);
        }

        if (method_exists($this->controller, 'setTeam')) {
            $this->controller->setTeam($team);
        }
    }
}