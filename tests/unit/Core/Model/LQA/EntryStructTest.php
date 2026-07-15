<?php

namespace Matecat\Core\Model\LQA;

use Matecat\TestHelpers\AbstractTest;
use Model\Exceptions\NotFoundException;
use Model\LQA\CategoryStruct;
use Model\LQA\EntryStruct;
use Model\LQA\EntryValidator;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use PHPUnit\Framework\Attributes\Test;

class EntryStructTest extends AbstractTest
{
    #[Test]
    public function constructor_sets_properties_from_array(): void
    {
        $struct = new EntryStruct([
            'id_segment' => 10,
            'id_job'     => 20,
            'id_category' => 5,
            'severity'   => 'critical',
            'source_page' => 1,
        ]);

        $this->assertSame(10, $struct->id_segment);
        $this->assertSame(20, $struct->id_job);
        $this->assertSame(5, $struct->id_category);
        $this->assertSame('critical', $struct->severity);
    }

    #[Test]
    public function ensureValid_delegates_to_validator(): void
    {
        $validator = $this->createMock(EntryValidator::class);
        $validator->expects($this->once())->method('ensureValid');

        $struct = new EntryStruct([]);
        $struct->ensureValid($validator);
    }

    #[Test]
    public function addComments_and_getComments(): void
    {
        $struct = $this->createStruct();

        $comments = [['text' => 'hello']];
        $struct->addComments($comments);
        $this->assertSame($comments, $struct->getComments());
    }

    #[Test]
    public function getDiff_returns_null_when_unset(): void
    {
        $struct = $this->createStruct();
        $this->assertNull($struct->getDiff());
    }

    #[Test]
    public function setDiff_and_getDiff(): void
    {
        $struct = $this->createStruct();
        $result = $struct->setDiff('some diff');
        $this->assertSame($struct, $result);
        $this->assertSame('some diff', $struct->getDiff());
    }

    #[Test]
    public function setDefaults_sets_translation_version_and_category(): void
    {
        $category = new CategoryStruct();
        $category->id = 42;
        $category->severities = json_encode([
            ['label' => 'minor', 'penalty' => 1.5],
            ['label' => 'critical', 'penalty' => 10.0],
        ]);

        $validator = $this->createMock(EntryValidator::class);
        $validator->expects($this->once())->method('ensureValid');
        $validator->category = $category;

        $translation = new SegmentTranslationStruct();
        $translation->version_number = 3;

        $dao = $this->createStub(SegmentTranslationDao::class);
        $dao->method('findBySegmentAndJob')->willReturn($translation);

        $struct = new EntryStruct([
            'id_segment'  => 1,
            'id_job'      => 2,
            'id_category' => 0,
            'severity'    => 'critical',
            'source_page' => 1,
        ]);

        $struct->setDefaults($validator, $dao);

        $this->assertSame(3, $struct->translation_version);
        $this->assertSame(42, $struct->id_category);
        $this->assertSame(10.0, $struct->penalty_points);
    }

    #[Test]
    public function setDefaults_throws_when_translation_not_found(): void
    {
        $validator = $this->createStub(EntryValidator::class);

        $dao = $this->createStub(SegmentTranslationDao::class);
        $dao->method('findBySegmentAndJob')->willReturn(null);

        $struct = new EntryStruct([
            'id_segment'  => 1,
            'id_job'      => 2,
            'id_category' => 0,
            'severity'    => 'minor',
            'source_page' => 1,
        ]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Segment translation not found');
        $struct->setDefaults($validator, $dao);
    }

    #[Test]
    public function setDefaults_throws_when_category_null(): void
    {
        $validator = $this->createStub(EntryValidator::class);
        $validator->category = null;

        $translation = new SegmentTranslationStruct();
        $translation->version_number = 1;

        $dao = $this->createStub(SegmentTranslationDao::class);
        $dao->method('findBySegmentAndJob')->willReturn($translation);

        $struct = new EntryStruct([
            'id_segment'  => 1,
            'id_job'      => 2,
            'id_category' => 0,
            'severity'    => 'minor',
            'source_page' => 1,
        ]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Category not found');
        $struct->setDefaults($validator, $dao);
    }

    #[Test]
    public function setDefaults_throws_when_category_id_null(): void
    {
        $category = new CategoryStruct();
        $category->id = null;
        $category->severities = json_encode([['label' => 'minor', 'penalty' => 1.0]]);

        $validator = $this->createStub(EntryValidator::class);
        $validator->category = $category;

        $translation = new SegmentTranslationStruct();
        $translation->version_number = 1;

        $dao = $this->createStub(SegmentTranslationDao::class);
        $dao->method('findBySegmentAndJob')->willReturn($translation);

        $struct = new EntryStruct([
            'id_segment'  => 1,
            'id_job'      => 2,
            'id_category' => 0,
            'severity'    => 'minor',
            'source_page' => 1,
        ]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Category id is null');
        $struct->setDefaults($validator, $dao);
    }

    #[Test]
    public function setDefaults_penalty_null_when_severity_not_found(): void
    {
        $category = new CategoryStruct();
        $category->id = 42;
        $category->severities = json_encode([
            ['label' => 'minor', 'penalty' => 1.0],
        ]);

        $validator = $this->createStub(EntryValidator::class);
        $validator->category = $category;

        $translation = new SegmentTranslationStruct();
        $translation->version_number = 1;

        $dao = $this->createStub(SegmentTranslationDao::class);
        $dao->method('findBySegmentAndJob')->willReturn($translation);

        $struct = new EntryStruct([
            'id_segment'  => 1,
            'id_job'      => 2,
            'id_category' => 0,
            'severity'    => 'nonexistent',
            'source_page' => 1,
        ]);

        $struct->setDefaults($validator, $dao);

        $this->assertNull($struct->penalty_points);
    }

    #[Test]
    public function ensureStartAndStopPositionAreOrdered_swaps_offsets_same_node(): void
    {
        $struct = $this->createStruct();
        $struct->start_node = 1;
        $struct->end_node = 1;
        $struct->start_offset = 10;
        $struct->end_offset = 5;

        $struct->ensureStartAndStopPositionAreOrdered();

        $this->assertSame(5, $struct->start_offset);
        $this->assertSame(10, $struct->end_offset);
    }

    #[Test]
    public function ensureStartAndStopPositionAreOrdered_no_swap_when_already_ordered_same_node(): void
    {
        $struct = $this->createStruct();
        $struct->start_node = 1;
        $struct->end_node = 1;
        $struct->start_offset = 3;
        $struct->end_offset = 7;

        $struct->ensureStartAndStopPositionAreOrdered();

        $this->assertSame(3, $struct->start_offset);
        $this->assertSame(7, $struct->end_offset);
    }

    #[Test]
    public function ensureStartAndStopPositionAreOrdered_swaps_nodes_and_offsets(): void
    {
        $struct = $this->createStruct();
        $struct->start_node = 5;
        $struct->end_node = 2;
        $struct->start_offset = 10;
        $struct->end_offset = 20;

        $struct->ensureStartAndStopPositionAreOrdered();

        $this->assertSame(2, $struct->start_node);
        $this->assertSame(5, $struct->end_node);
        $this->assertSame(20, $struct->start_offset);
        $this->assertSame(10, $struct->end_offset);
    }

    #[Test]
    public function ensureStartAndStopPositionAreOrdered_no_swap_when_already_ordered(): void
    {
        $struct = $this->createStruct();
        $struct->start_node = 1;
        $struct->end_node = 3;
        $struct->start_offset = 5;
        $struct->end_offset = 10;

        $struct->ensureStartAndStopPositionAreOrdered();

        $this->assertSame(1, $struct->start_node);
        $this->assertSame(3, $struct->end_node);
        $this->assertSame(5, $struct->start_offset);
        $this->assertSame(10, $struct->end_offset);
    }

    #[Test]
    public function setDefaults_version_defaults_to_zero_when_null(): void
    {
        $category = new CategoryStruct();
        $category->id = 42;
        $category->severities = json_encode([['label' => 'minor', 'penalty' => 1.0]]);

        $validator = $this->createStub(EntryValidator::class);
        $validator->category = $category;

        $translation = new SegmentTranslationStruct();
        $translation->version_number = null;

        $dao = $this->createStub(SegmentTranslationDao::class);
        $dao->method('findBySegmentAndJob')->willReturn($translation);

        $struct = new EntryStruct([
            'id_segment'  => 1,
            'id_job'      => 2,
            'id_category' => 0,
            'severity'    => 'minor',
            'source_page' => 1,
        ]);

        $struct->setDefaults($validator, $dao);

        $this->assertSame(0, $struct->translation_version);
    }

    private function createStruct(): EntryStruct
    {
        return new EntryStruct([
            'id_segment'  => 1,
            'id_job'      => 2,
            'id_category' => 3,
            'severity'    => 'minor',
            'source_page' => 1,
        ]);
    }
}
