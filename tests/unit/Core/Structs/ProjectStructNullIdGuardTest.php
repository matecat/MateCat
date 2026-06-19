<?php


namespace Matecat\Core\Structs;

use DomainException;
use Matecat\TestHelpers\AbstractTest;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;

class ProjectStructNullIdGuardTest extends AbstractTest
{
    #[Test]
    public function getMetadataValue_throws_when_id_is_null(): void
    {
        $struct = new ProjectStruct();
        $struct->id = null;

        $this->expectException(DomainException::class);
        $struct->getMetadataValue('key');
    }

}
