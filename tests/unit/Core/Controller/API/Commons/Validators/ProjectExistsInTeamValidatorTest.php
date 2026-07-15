<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\ProjectExistsInTeamValidator;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller exposing the only seam ProjectExistsInTeamValidator
 * touches: getRequest() (read in Base ctor and in _validate via $this->request).
 */
class ProjectExistsInTeamValidatorTestController extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Pure-logic suite for ProjectExistsInTeamValidator — compares the request
 * id_team against the project's id_team. No DB involved.
 */
class ProjectExistsInTeamValidatorTest extends AbstractTest
{
    private const int TEAM_ID = 9_933_000;

    private function makeValidator(int $requestTeamId, int $projectTeamId): ProjectExistsInTeamValidator
    {
        $controller = new ProjectExistsInTeamValidatorTestController();

        $ctrlRef = new ReflectionClass(KleinController::class);
        $p = $ctrlRef->getProperty('request');
        $p->setAccessible(true);
        $p->setValue($controller, new Request(
            ['id_team' => (string) $requestTeamId],
            [],
            [],
            ['REQUEST_URI' => '/api/v2/projects', 'REQUEST_METHOD' => 'GET']
        ));

        $project = new ProjectStruct();
        $project->id_team = $projectTeamId;

        $validator = new ProjectExistsInTeamValidator($controller);
        $validator->setProject($project);

        return $validator;
    }

    // ─── request id_team matches the project's team => passes ───

    #[Test]
    public function validates_when_request_team_matches_project_team(): void
    {
        $validator = $this->makeValidator(self::TEAM_ID, self::TEAM_ID);

        // must not throw
        $validator->_validate();
        $this->assertTrue(true);
    }

    // ─── request id_team differs => NotFoundException 404 ───

    #[Test]
    public function throws_not_found_when_request_team_differs_from_project_team(): void
    {
        $validator = $this->makeValidator(self::TEAM_ID + 1, self::TEAM_ID);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $validator->_validate();
    }

    // ─── setProject returns the validator (fluent) ───

    #[Test]
    public function set_project_is_fluent(): void
    {
        $controller = new ProjectExistsInTeamValidatorTestController();
        $ctrlRef = new ReflectionClass(KleinController::class);
        $p = $ctrlRef->getProperty('request');
        $p->setAccessible(true);
        $p->setValue($controller, new Request([], [], [], ['REQUEST_URI' => '/', 'REQUEST_METHOD' => 'GET']));

        $project = new ProjectStruct();
        $project->id_team = self::TEAM_ID;

        $validator = new ProjectExistsInTeamValidator($controller);
        $this->assertSame($validator, $validator->setProject($project));
    }
}
