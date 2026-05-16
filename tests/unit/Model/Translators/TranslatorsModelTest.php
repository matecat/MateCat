<?php

namespace unit\Model\Translators;

use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Translators\TranslatorsModel;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TypeError;

class TranslatorsModelTest extends TestCase
{
    private function makeJobStructWithProject(
        ?int $id = 1,
        ?string $password = 'abc123',
        string $source = 'en-US',
        string $target = 'it-IT',
    ): JobStruct {
        $project = new ProjectStruct();
        $project->id = 999;
        $project->name = 'Test Project';

        $job = $this->createStub(JobStruct::class);
        $job->id = $id;
        $job->password = $password;
        $job->source = $source;
        $job->target = $target;
        $job->id_project = 999;

        $job->method('getProject')->willReturn($project);

        return $job;
    }

    private function makeModel(
        ?int $id = 1,
        ?string $password = 'abc123',
    ): TranslatorsModel {
        $job = $this->makeJobStructWithProject(id: $id, password: $password);

        return new TranslatorsModel($job);
    }

    #[Test]
    public function constructorThrowsWhenPasswordIsNull(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('password cannot be null');

        $project = new ProjectStruct();
        $project->id = 1;
        $project->name = 'Test';

        $job = $this->createStub(JobStruct::class);
        $job->id = 1;
        $job->password = null;
        $job->method('getProject')->willReturn($project);

        new TranslatorsModel($job);
    }

    #[Test]
    public function constructorSetsPropertiesFromJobStruct(): void
    {
        $model = $this->makeModel(id: 42, password: 'testpwd');

        $reflection = new \ReflectionClass($model);

        $idProp = $reflection->getProperty('id_job');
        $this->assertSame(42, $idProp->getValue($model));

        $pwProp = $reflection->getProperty('job_password');
        $this->assertSame('testpwd', $pwProp->getValue($model));
    }

    #[Test]
    public function setDeliveryDateWithIntegerTimestamp(): void
    {
        $model = $this->makeModel();
        $timestamp = 1700000000;

        $result = $model->setDeliveryDate($timestamp);

        $this->assertSame($model, $result);
        $reflection = new \ReflectionProperty($model, 'delivery_date');
        $this->assertSame($timestamp, $reflection->getValue($model));
    }

    #[Test]
    public function setDeliveryDateWithNumericString(): void
    {
        $model = $this->makeModel();

        $model->setDeliveryDate('1700000000');

        $reflection = new \ReflectionProperty($model, 'delivery_date');
        $this->assertSame(1700000000, $reflection->getValue($model));
    }

    #[Test]
    public function setDeliveryDateWithValidDateString(): void
    {
        $model = $this->makeModel();

        $model->setDeliveryDate('2025-06-15 10:00:00');

        $reflection = new \ReflectionProperty($model, 'delivery_date');
        $this->assertSame(strtotime('2025-06-15 10:00:00'), $reflection->getValue($model));
    }

    #[Test]
    public function setDeliveryDateThrowsOnInvalidDateString(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Invalid date string');

        $model = $this->makeModel();
        $model->setDeliveryDate('not-a-valid-date-at-all-xyz');
    }

    #[Test]
    public function setEmailStoresValue(): void
    {
        $model = $this->makeModel();

        $result = $model->setEmail('test@example.com');

        $this->assertSame($model, $result);
        $reflection = new \ReflectionProperty($model, 'email');
        $this->assertSame('test@example.com', $reflection->getValue($model));
    }

    #[Test]
    public function setNewJobPasswordStoresValue(): void
    {
        $model = $this->makeModel();

        $result = $model->setNewJobPassword('newpass');

        $this->assertSame($model, $result);
        $reflection = new \ReflectionProperty($model, 'job_password');
        $this->assertSame('newpass', $reflection->getValue($model));
    }

    #[Test]
    public function setJobOwnerTimezoneStoresValue(): void
    {
        $model = $this->makeModel();

        $result = $model->setJobOwnerTimezone(-5.5);

        $this->assertSame($model, $result);
        $reflection = new \ReflectionProperty($model, 'job_owner_timezone');
        $this->assertSame(-5.5, $reflection->getValue($model));
    }

    #[Test]
    public function setUserInviteStoresUser(): void
    {
        $model = $this->makeModel();

        $user = new UserStruct();
        $user->uid = 100;
        $user->email = 'inviter@example.com';

        $result = $model->setUserInvite($user);

        $this->assertSame($model, $result);
        $reflection = new \ReflectionProperty($model, 'callingUser');
        $this->assertSame($user, $reflection->getValue($model));
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getTranslatorReturnsNullWhenNoTranslatorExists(): void
    {
        $model = $this->makeModel(id: 999999, password: 'nonexistent_pw');

        $result = $model->getTranslator(0);

        $this->assertNull($result);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function updateThrowsWhenCallingUserUidIsNull(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('Calling user uid cannot be null');

        $model = $this->makeModel();

        $user = new UserStruct();
        $user->uid = null;
        $user->email = 'user@example.com';

        $model->setUserInvite($user)
            ->setEmail('translator@example.com')
            ->setDeliveryDate(time() + 86400);

        $model->update();
    }
}
