<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Teams\MembershipDao;
use Model\Users\UserDao;
use ReflectionException;
use RuntimeException;
use TypeError;
use View\API\V2\Json\Membership;

/**
 * Returns the public (membership-free) team members list used for comment attribution.
 *
 * Access is granted by the job password capability (unguessable) resolved in
 * registerValidators(), NOT by a guessable team name — this prevents harvesting a team's
 * member names by guessing team ids/names (CWE-639). Only the public projection is
 * emitted (uid + first/last name; no email).
 */
class JobTeamMembersController extends KleinController
{
    protected ProjectStruct $project;

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));

        $validator = new ChunkPasswordValidator($this);
        $validator->onSuccess(function () use ($validator) {
            $this->project = $validator->getChunk()->getProject(new ProjectDao($this->getDatabase()));
        });
        $this->appendValidator($validator);
    }

    /**
     * @throws RuntimeException
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function members(): void
    {
        $idTeam = $this->project->id_team
            ?? throw new RuntimeException('Project has no team');

        $memberships = (new MembershipDao($this->getDatabase()))->setCacheTTL(60 * 60 * 24)->getMemberListByTeamId($idTeam);
        $formatter   = new Membership($memberships, new UserDao($this->getDatabase()));
        $this->response->json($formatter->renderPublic());
    }
}
