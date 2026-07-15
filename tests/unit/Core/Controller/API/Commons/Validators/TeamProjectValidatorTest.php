<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\TeamProjectValidator;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\Exceptions\NotFoundException;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller stub exposing the seams TeamProjectValidator touches:
 * getRequest() (from KleinController) and setProject() (so method_exists branch fires if present).
 */
class TeamProjectValidatorTestController extends KleinController
{
    public function __construct()
    {
        // intentionally empty — skip parent wiring
    }
}

/**
 * Pure-logic validator suite. No DB seeding needed.
 * Reserved ID block base = 9_929_000.
 */
class TeamProjectValidatorTest extends AbstractTest
{
    private const int B = 9_929_000;
    private const string EMAIL = 'ctrltest_9929000@example.org';

    private TeamProjectValidatorTestController $controller;
    private ReflectionClass $ctrlRef;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new TeamProjectValidatorTestController();
        $this->ctrlRef    = new ReflectionClass(KleinController::class);

        // Provide a minimal request so Base::__construct() does not fail
        $this->setCtrlProp(
            'request',
            new Request([], [], [], ['REQUEST_URI' => '/api/v2/projects', 'REQUEST_METHOD' => 'GET'])
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ─── helpers ───────────────────────────────────────────────────────────────

    private function setCtrlProp(string $name, mixed $value): void
    {
        $c = $this->ctrlRef;
        while ($c !== false && !$c->hasProperty($name)) {
            $c = $c->getParentClass();
        }
        $p = $c->getProperty($name);
        $p->setAccessible(true);
        $p->setValue($this->controller, $value);
    }

    private function makeValidator(): TeamProjectValidator
    {
        return new TeamProjectValidator($this->controller);
    }

    // ─── happy path ────────────────────────────────────────────────────────────

    #[Test]
    public function validates_successfully_when_project_has_id(): void
    {
        $project     = new ProjectStruct();
        $project->id = self::B;

        $validator = $this->makeValidator();
        $validator->setProject($project);

        // Must not throw
        $validator->_validate();

        $this->assertTrue(true); // reached here without exception
    }

    // ─── failure: project id is null ──────────────────────────────────────────

    #[Test]
    public function throws_not_found_when_project_id_is_null(): void
    {
        $project     = new ProjectStruct();
        $project->id = null; // explicit null id

        $validator = $this->makeValidator();
        $validator->setProject($project);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $validator->_validate();
    }

    // ─── failure: project id is zero (falsy) ──────────────────────────────────

    #[Test]
    public function throws_not_found_when_project_id_is_zero(): void
    {
        $project     = new ProjectStruct();
        $project->id = 0;

        $validator = $this->makeValidator();
        $validator->setProject($project);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $validator->_validate();
    }

    // ─── failure: setProject never called (uninitialized property) ────────────

    #[Test]
    public function throws_not_found_when_project_not_set(): void
    {
        $validator = $this->makeValidator();
        // setProject() is never called — $this->project is uninitialized

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $validator->_validate();
    }

    // ─── setProject returns $this (fluent interface) ──────────────────────────

    #[Test]
    public function set_project_returns_validator_instance(): void
    {
        $project     = new ProjectStruct();
        $project->id = self::B;

        $validator = $this->makeValidator();
        $result    = $validator->setProject($project);

        $this->assertSame($validator, $result);
    }
}
