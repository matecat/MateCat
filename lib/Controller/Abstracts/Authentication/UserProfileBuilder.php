<?php

namespace Controller\Abstracts\Authentication;

use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Exception;
use Model\ConnectedServices\ConnectedServiceDao;
use Model\DataAccess\IDatabase;
use Model\Teams\MembershipDao;
use Model\Teams\TeamDao;
use Model\Teams\TeamModel;
use Model\Users\MetadataDao;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use Predis\ClientInterface;
use ReflectionException;
use RuntimeException;
use TypeError;
use View\API\App\Json\UserProfile;

/**
 * Builds the user-profile payload (teams + connected services) for a user.
 *
 * Extracted verbatim from AuthenticationHelper::getUserProfile() so the
 * collaborators (MembershipDao, ConnectedServiceDao) become injectable and
 * the behavior is unit-testable without the global database singleton.
 */
class UserProfileBuilder
{
    public function __construct(
        private readonly MembershipDao $membershipDao,
        private readonly ConnectedServiceDao $connectedServiceDao,
        private readonly UserDao $userDao,
        private readonly TeamDao $teamDao,
        private readonly MetadataDao $metadataDao,
        private readonly ClientInterface $redis,
    ) {
    }

    /**
     * Composition root for the build: wires the DAOs from a database handle and takes the one Redis
     * connection the render needs. Shared by every caller that builds a profile, so the wiring is
     * not duplicated at each of them.
     */
    public static function fromDatabase(IDatabase $db, ClientInterface $redis): self
    {
        return new self(
            new MembershipDao($db),
            new ConnectedServiceDao($db),
            new UserDao($db),
            new TeamDao($db),
            new MetadataDao($db),
            $redis,
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ReflectionException
     * @throws EnvironmentIsBrokenException
     * @throws RuntimeException
     * @throws Exception
     * @throws TypeError
     */
    public function build(UserStruct $user): array
    {
        $metadata = $user->getMetadataAsKeyValue($this->metadataDao);

        $this->membershipDao->setCacheTTL(60 * 5);
        $userTeams = array_map(
            function ($team) {
                $teamModel = new TeamModel($team, $this->userDao, $this->teamDao);
                $teamModel->updateMembersProjectsCount();

                return $team;
            },
            $this->membershipDao->findUserTeams($user) ?? []
        );

        $services = $this->connectedServiceDao->findServicesByUser($user);

        return (new UserProfile())->renderItem(
            $user,
            $userTeams,
            $this->userDao,
            $this->redis,
            $services,
            $metadata
        );
    }
}
