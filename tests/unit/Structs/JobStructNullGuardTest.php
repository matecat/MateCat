<?php

use Model\Jobs\JobStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class JobStructNullGuardTest extends AbstractTest
{
    #[Test]
    public function getPeeForTranslatedSegments_throws_when_id_is_null(): void
    {
        $struct = new JobStruct();
        $struct->id = null;
        $struct->password = 'pass';

        $this->expectException(DomainException::class);
        $struct->getPeeForTranslatedSegments();
    }

    #[Test]
    public function getPeeForTranslatedSegments_throws_when_password_is_null(): void
    {
        $struct = new JobStruct();
        $struct->id = 1;
        $struct->password = null;

        $this->expectException(DomainException::class);
        $struct->getPeeForTranslatedSegments();
    }

    #[Test]
    public function getSegments_throws_when_id_is_null(): void
    {
        $struct = new JobStruct();
        $struct->id = null;
        $struct->password = 'pass';

        $this->expectException(DomainException::class);
        $struct->getSegments();
    }

    #[Test]
    public function getSegments_throws_when_password_is_null(): void
    {
        $struct = new JobStruct();
        $struct->id = 1;
        $struct->password = null;

        $this->expectException(DomainException::class);
        $struct->getSegments();
    }

    #[Test]
    public function getChunks_throws_when_id_is_null(): void
    {
        $struct = new JobStruct();
        $struct->id = null;

        $this->expectException(DomainException::class);
        $struct->getChunks();
    }
}
