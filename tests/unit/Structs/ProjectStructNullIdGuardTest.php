<?php

use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class ProjectStructNullIdGuardTest extends AbstractTest
{
    #[Test]
    public function getJobs_throws_when_id_is_null(): void
    {
        $struct = new ProjectStruct();
        $struct->id = null;

        $this->expectException(DomainException::class);
        $struct->getJobs();
    }

    #[Test]
    public function setMetadata_throws_when_id_is_null(): void
    {
        $struct = new ProjectStruct();
        $struct->id = null;

        $this->expectException(DomainException::class);
        $struct->setMetadata('key', 'value');
    }

    #[Test]
    public function getMetadataValue_throws_when_id_is_null(): void
    {
        $struct = new ProjectStruct();
        $struct->id = null;

        $this->expectException(DomainException::class);
        $struct->getMetadataValue('key');
    }

    #[Test]
    public function getAllMetadata_throws_when_id_is_null(): void
    {
        $struct = new ProjectStruct();
        $struct->id = null;

        $this->expectException(DomainException::class);
        $struct->getAllMetadata();
    }

    #[Test]
    public function getChunks_throws_when_id_is_null(): void
    {
        $struct = new ProjectStruct();
        $struct->id = null;

        $this->expectException(DomainException::class);
        $struct->getChunks();
    }
}
