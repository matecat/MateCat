<?php

namespace unit\Model\Files;

use Model\DataAccess\ShapelessConcreteStruct;
use Model\Files\FileDao;
use Model\Files\FilesInfoUtility;
use Model\Files\FilesPartsDao;
use Model\Files\FilesPartsStruct;
use Model\Files\MetadataDao;
use Model\Files\MetadataStruct;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use RuntimeException;

class FilesInfoUtilityTest extends AbstractTest
{
    private function makeChunk(?int $projectId): JobStruct
    {
        $project = new ProjectStruct();
        $project->id = $projectId;

        return new class($project) extends JobStruct {
            public function __construct(private ProjectStruct $projectStruct)
            {
                $this->job_first_segment = 1;
                $this->job_last_segment  = 10;
            }

            public function getProject(int $ttl = 86400): ProjectStruct
            {
                return $this->projectStruct;
            }
        };
    }

    #[Test]
    public function constructor_throws_when_project_id_is_null(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Project ID must not be null');

        new FilesInfoUtility($this->makeChunk(null));
    }

    #[Test]
    public function constructor_succeeds_with_valid_project_id(): void
    {
        $utility = new FilesInfoUtility($this->makeChunk(42));
        $this->assertInstanceOf(FilesInfoUtility::class, $utility);
    }

    #[Test]
    public function get_instructions_returns_null_when_file_not_in_project(): void
    {
        $fileDao = $this->createStub(FileDao::class);
        $fileDao->method('isFileInProject')->willReturn(0);

        $utility = new FilesInfoUtility($this->makeChunk(1), fileDao: $fileDao);

        $this->assertNull($utility->getInstructions(99));
    }

    #[Test]
    public function get_instructions_returns_null_when_no_key_found(): void
    {
        $fileDao = $this->createStub(FileDao::class);
        $fileDao->method('isFileInProject')->willReturn(1);

        $metadataDao = $this->createStub(MetadataDao::class);
        $metadataDao->method('get')->willReturn(null);

        $utility = new FilesInfoUtility($this->makeChunk(1), metadataDao: $metadataDao, fileDao: $fileDao);

        $this->assertNull($utility->getInstructions(5));
    }

    #[Test]
    public function get_instructions_returns_instructions_value(): void
    {
        $fileDao = $this->createStub(FileDao::class);
        $fileDao->method('isFileInProject')->willReturn(1);

        $struct = new MetadataStruct();
        $struct->value = 'Translate carefully';

        $metadataDao = $this->createStub(MetadataDao::class);
        $metadataDao->method('get')->willReturn($struct);

        $utility = new FilesInfoUtility($this->makeChunk(1), metadataDao: $metadataDao, fileDao: $fileDao);

        $this->assertSame(['instructions' => 'Translate carefully'], $utility->getInstructions(5));
    }

    #[Test]
    public function get_instructions_falls_back_to_mtc_instructions_key(): void
    {
        $fileDao = $this->createStub(FileDao::class);
        $fileDao->method('isFileInProject')->willReturn(1);

        $struct = new MetadataStruct();
        $struct->value = 'MTC instructions';

        $metadataDao = $this->createStub(MetadataDao::class);
        $metadataDao->method('get')->willReturnCallback(
            fn(int $p, int $f, string $key) => $key === 'mtc:instructions' ? $struct : null
        );

        $utility = new FilesInfoUtility($this->makeChunk(1), metadataDao: $metadataDao, fileDao: $fileDao);

        $this->assertSame(['instructions' => 'MTC instructions'], $utility->getInstructions(5));
    }

    #[Test]
    public function set_instructions_returns_false_when_file_not_in_project(): void
    {
        $fileDao = $this->createStub(FileDao::class);
        $fileDao->method('isFileInProject')->willReturn(0);

        $utility = new FilesInfoUtility($this->makeChunk(1), fileDao: $fileDao);

        $this->assertFalse($utility->setInstructions(99, 'Do this'));
    }

    #[Test]
    public function set_instructions_calls_update_when_instructions_exist(): void
    {
        $fileDao = $this->createStub(FileDao::class);
        $fileDao->method('isFileInProject')->willReturn(1);

        $metadataDao = $this->createMock(MetadataDao::class);
        $metadataDao->method('get')->willReturn(new MetadataStruct());
        $metadataDao->expects($this->once())->method('update')
            ->with(1, 5, 'instructions', 'new text');
        $metadataDao->expects($this->never())->method('insert');

        $utility = new FilesInfoUtility($this->makeChunk(1), metadataDao: $metadataDao, fileDao: $fileDao);

        $this->assertTrue($utility->setInstructions(5, 'new text'));
    }

    #[Test]
    public function set_instructions_calls_insert_when_no_existing(): void
    {
        $fileDao = $this->createStub(FileDao::class);
        $fileDao->method('isFileInProject')->willReturn(1);

        $metadataDao = $this->createMock(MetadataDao::class);
        $metadataDao->method('get')->willReturn(null);
        $metadataDao->expects($this->once())->method('insert')
            ->with(1, 5, 'instructions', 'brand new');
        $metadataDao->expects($this->never())->method('update');

        $utility = new FilesInfoUtility($this->makeChunk(1), metadataDao: $metadataDao, fileDao: $fileDao);

        $this->assertTrue($utility->setInstructions(5, 'brand new'));
    }

    #[Test]
    public function get_info_without_metadata_returns_rendered_structure(): void
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getFilesInfoInJob')->willReturn([]);

        $utility = new FilesInfoUtility($this->makeChunk(1), jobDao: $jobDao);

        $result = $utility->getInfo(false);

        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey('first_segment', $result);
        $this->assertArrayHasKey('last_segment', $result);
        $this->assertSame([], $result['files']);
    }

    private function makeFileStruct(int $idFile): ShapelessConcreteStruct
    {
        $file = new ShapelessConcreteStruct();
        $file->id_file        = $idFile;
        $file->first_segment  = 1;
        $file->last_segment   = 5;
        $file->file_name      = 'test.xliff';
        $file->raw_words      = '100';
        $file->weighted_words = '90';
        $file->standard_words = '95';
        return $file;
    }

    #[Test]
    public function get_info_with_flat_metadata_key(): void
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getFilesInfoInJob')->willReturn([$this->makeFileStruct(7)]);

        $metadatum = new MetadataStruct();
        $metadatum->files_parts_id = null;
        $metadatum->key   = 'instructions';
        $metadatum->value = 'Be careful';

        $metadataDao = $this->createStub(MetadataDao::class);
        $metadataDao->method('getByJobIdProjectAndIdFile')->willReturn([$metadatum]);

        $utility = new FilesInfoUtility(
            $this->makeChunk(1),
            jobDao: $jobDao,
            metadataDao: $metadataDao,
            filesPartsDao: $this->createStub(FilesPartsDao::class)
        );

        $result = $utility->getInfo(true);

        $this->assertCount(1, $result['files']);
        $this->assertSame('Be careful', $result['files'][0]['metadata']['instructions']);
    }

    #[Test]
    public function get_info_uses_files_parts_dao_when_metadata_is_null(): void
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getFilesInfoInJob')->willReturn([$this->makeFileStruct(7)]);

        $metadataDao = $this->createStub(MetadataDao::class);
        $metadataDao->method('getByJobIdProjectAndIdFile')->willReturn(null);

        $filePart = new FilesPartsStruct();
        $filePart->id = 42;

        $filesPartsDao = $this->createStub(FilesPartsDao::class);
        $filesPartsDao->method('getByFileId')->willReturn([$filePart]);

        $utility = new FilesInfoUtility(
            $this->makeChunk(1),
            jobDao: $jobDao,
            metadataDao: $metadataDao,
            filesPartsDao: $filesPartsDao
        );

        $result = $utility->getInfo(true);

        $this->assertSame([['id' => 42]], $result['files'][0]['metadata']['files_parts']);
        $this->assertNull($result['files'][0]['metadata']['instructions']);
    }

    #[Test]
    public function get_info_reindexes_files_parts_from_metadata(): void
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getFilesInfoInJob')->willReturn([$this->makeFileStruct(7)]);

        $metadatum = new MetadataStruct();
        $metadatum->files_parts_id = 10;
        $metadatum->key   = 'color';
        $metadatum->value = 'blue';

        $metadataDao = $this->createStub(MetadataDao::class);
        $metadataDao->method('getByJobIdProjectAndIdFile')->willReturn([$metadatum]);

        $utility = new FilesInfoUtility(
            $this->makeChunk(1),
            jobDao: $jobDao,
            metadataDao: $metadataDao,
            filesPartsDao: $this->createStub(FilesPartsDao::class)
        );

        $result = $utility->getInfo(true);

        $filesMeta = $result['files'][0]['metadata'];
        $this->assertSame(10, $filesMeta['files_parts'][0]['id']);
        $this->assertSame('blue', $filesMeta['files_parts'][0]['color']);
    }
}
