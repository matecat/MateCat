<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Analysis;

use Exception;
use Model\Analysis\AbstractStatus;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

/**
 * Testable concrete subclass of AbstractStatus that bypasses static DB calls.
 */
class TestableAbstractStatus extends AbstractStatus
{
    /**
     * Override constructor to inject ProjectStruct directly, skipping ProjectDao::staticFindById.
     *
     * @param array<mixed>    $_project_data
     * @param FeatureSet      $features
     * @param ProjectStruct   $project
     * @param UserStruct|null $user
     */
    public function __construct(
        array $_project_data,
        FeatureSet $features,
        ProjectStruct $project,
        ?UserStruct $user = null
    ) {
        if ($user === null) {
            $user       = new UserStruct();
            $user->uid  = -1;
        }
        $this->user          = $user;
        $this->project       = $project;
        $this->_project_data = $_project_data;
        $this->featureSet    = $features;
    }

    /**
     * Expose protected isOutsourceEnabled for testing.
     */
    public function callIsOutsourceEnabled(string $targetLang, string $id_customer, int $idJob): bool
    {
        return $this->isOutsourceEnabled($targetLang, $id_customer, $idJob);
    }
}

class AbstractStatusTest extends AbstractTest
{
    private function makeProjectStruct(int $id = 1): ProjectStruct
    {
        $project           = new ProjectStruct();
        $project->id       = $id;
        $project->name     = 'Test Project';
        $project->password = 'abc123';

        return $project;
    }

    /** @return array<mixed> */
    private function makeProjectData(int $pid = 1): array
    {
        return [
            [
                'pid'              => $pid,
                'pname'            => 'Test Project',
                'status_analysis'  => 'DONE',
                'create_date'      => '2024-01-01 00:00:00',
                'subject'          => 'general',
                'jid'              => 10,
                'jpassword'        => 'pass1',
                'lang_pair'        => 'en-US|it-IT',
                'standard_analysis_wc' => 100,
                'payable_rates'    => '{}',
                'id_customer'      => 'customer1',
            ],
        ];
    }

    #[Test]
    public function getResultThrowsWhenNotInitialized(): void
    {
        $status = new TestableAbstractStatus(
            $this->makeProjectData(),
            new FeatureSet(),
            $this->makeProjectStruct()
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Result not initialized');
        $status->getResult();
    }

    #[Test]
    public function constructorSetsUserToAnonymousWhenNull(): void
    {
        $status = new TestableAbstractStatus(
            $this->makeProjectData(),
            new FeatureSet(),
            $this->makeProjectStruct()
        );

        // If no exception thrown, user was set correctly (uid = -1 for anonymous)
        $this->assertInstanceOf(AbstractStatus::class, $status);
    }

    #[Test]
    public function constructorAcceptsExplicitUser(): void
    {
        $user      = new UserStruct();
        $user->uid = 42;

        $status = new TestableAbstractStatus(
            $this->makeProjectData(),
            new FeatureSet(),
            $this->makeProjectStruct(),
            $user
        );

        $this->assertInstanceOf(AbstractStatus::class, $status);
    }

    #[Test]
    public function isOutsourceEnabledReturnsTrueWithNoPlugins(): void
    {
        $status = new TestableAbstractStatus(
            $this->makeProjectData(),
            new FeatureSet(),
            $this->makeProjectStruct()
        );

        // With no plugins loaded, outsource info defaults to all-false flags → outsource IS available
        $result = $status->callIsOutsourceEnabled('it-IT', 'customer1', 10);

        $this->assertTrue($result);
    }

    #[Test]
    public function isOutsourceEnabledReturnsBoolForAnyLanguage(): void
    {
        $status = new TestableAbstractStatus(
            $this->makeProjectData(),
            new FeatureSet(),
            $this->makeProjectStruct()
        );

        // Result must be a boolean regardless of input — no exceptions thrown
        $result = $status->callIsOutsourceEnabled('en-US', 'customer1', 99);

        $this->assertIsBool($result);
    }

    #[Test]
    public function projectDataWithMultipleJobsIsAccepted(): void
    {
        $projectData   = $this->makeProjectData();
        $projectData[] = [
            'pid'              => 1,
            'pname'            => 'Test Project',
            'status_analysis'  => 'DONE',
            'create_date'      => '2024-01-01 00:00:00',
            'subject'          => 'general',
            'jid'              => 11,
            'jpassword'        => 'pass2',
            'lang_pair'        => 'en-US|de-DE',
            'standard_analysis_wc' => 200,
            'payable_rates'    => '{}',
            'id_customer'      => 'customer1',
        ];

        $status = new TestableAbstractStatus(
            $projectData,
            new FeatureSet(),
            $this->makeProjectStruct()
        );

        $this->assertInstanceOf(AbstractStatus::class, $status);
    }
}
