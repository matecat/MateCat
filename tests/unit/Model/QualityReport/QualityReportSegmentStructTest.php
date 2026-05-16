<?php

declare(strict_types=1);

namespace unit\Model\QualityReport;

use DivisionByZeroError;
use Model\Jobs\MetadataDao;
use Model\QualityReport\QualityReportSegmentStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class QualityReportSegmentStructTest extends AbstractTest
{
    private function createStruct(array $overrides = []): QualityReportSegmentStruct
    {
        $defaults = [
            'sid' => 1,
            'target' => 'en-US',
            'segment' => 'Hello world',
            'raw_word_count' => 2,
            'translation' => 'Ciao mondo',
            'version' => 1,
            'ice_locked' => false,
            'status' => 'TRANSLATED',
            'time_to_edit' => 5000,
            'filename' => 'test.xliff',
            'id_file' => 1,
            'warning' => false,
            'suggestion_match' => 100,
            'suggestion_source' => 'TM',
            'suggestion' => 'Ciao mondo',
            'edit_distance' => 0,
            'locked' => false,
            'match_type' => '100%',
            'warnings' => [],
            'pee' => 0.0,
            'ice_modified' => false,
            'secs_per_word' => 0.0,
            'parsed_time_to_edit' => [],
            'last_translation' => '',
            'last_revisions' => [],
            'pee_translation_revise' => 0.0,
            'pee_translation_suggestion' => 0.0,
            'version_number' => 0,
            'source_page' => null,
        ];

        $merged = array_merge($defaults, $overrides);

        $struct = new QualityReportSegmentStruct();
        foreach ($merged as $key => $value) {
            $struct->$key = $value;
        }

        return $struct;
    }

    #[Test]
    public function GetSecsPerWord(): void
    {
        $struct = $this->createStruct([
            'time_to_edit' => 10000, // 10s
            'raw_word_count' => 5,
        ]);

        // (10000 / 1000) / 5 = 2.0
        $this->assertSame(2.0, $struct->getSecsPerWord());
    }

    #[Test]
    public function GetSecsPerWordThrowsOnZeroWordCount(): void
    {
        $struct = $this->createStruct([
            'time_to_edit' => 10000,
            'raw_word_count' => 0,
        ]);

        $this->expectException(DivisionByZeroError::class);
        $struct->getSecsPerWord();
    }

    #[Test]
    public function GetSecsPerWordWithSmallValues(): void
    {
        $struct = $this->createStruct([
            'time_to_edit' => 3500, // 3.5s
            'raw_word_count' => 3,
        ]);

        // (3500 / 1000) / 3 ≈ 1.2 (rounded to 1 decimal)
        $this->assertSame(1.2, $struct->getSecsPerWord());
    }

    #[Test]
    public function IsICEReturnsTrueWhenMatchTypeIsICEAndLocked(): void
    {
        $struct = $this->createStruct([
            'match_type' => 'ICE',
            'locked' => true,
        ]);

        $this->assertTrue($struct->isICE());
    }

    #[Test]
    public function IsICEReturnsFalseWhenNotLocked(): void
    {
        $struct = $this->createStruct([
            'match_type' => 'ICE',
            'locked' => false,
        ]);

        $this->assertFalse($struct->isICE());
    }

    #[Test]
    public function IsICEReturnsFalseWhenMatchTypeIsNot_ICE(): void
    {
        $struct = $this->createStruct([
            'match_type' => '100%',
            'locked' => true,
        ]);

        $this->assertFalse($struct->isICE());
    }

    #[Test]
    public function IsICEModifiedReturnsTrueWhenVersionNonZeroAndICE(): void
    {
        $struct = $this->createStruct([
            'match_type' => 'ICE',
            'locked' => true,
            'version_number' => 2,
        ]);

        $this->assertTrue($struct->isICEModified());
    }

    #[Test]
    public function IsICEModifiedReturnsFalseWhenVersionIsZero(): void
    {
        $struct = $this->createStruct([
            'match_type' => 'ICE',
            'locked' => true,
            'version_number' => 0,
        ]);

        $this->assertFalse($struct->isICEModified());
    }

    #[Test]
    public function IsICEModifiedReturnsFalseWhenNotICE(): void
    {
        $struct = $this->createStruct([
            'match_type' => '100%',
            'locked' => false,
            'version_number' => 2,
        ]);

        $this->assertFalse($struct->isICEModified());
    }

    #[Test]
    public function GetPEEReturnsZeroWhenTranslationIsEmpty(): void
    {
        $struct = $this->createStruct([
            'translation' => null,
            'suggestion' => 'some suggestion',
        ]);

        $this->assertSame(0.0, $struct->getPEE());
    }

    #[Test]
    public function GetPEEReturnsZeroWhenSuggestionIsEmpty(): void
    {
        $struct = $this->createStruct([
            'translation' => 'some translation',
            'suggestion' => null,
        ]);

        $this->assertSame(0.0, $struct->getPEE());
    }

    #[Test]
    public function GetPEEReturnsFloatWhenBothPresent(): void
    {
        $struct = $this->createStruct([
            'translation' => 'Hello world',
            'suggestion' => 'Hello world',
            'target' => 'en-US',
        ]);

        // Same string → PEE should be 0
        $this->assertSame(0.0, $struct->getPEE());
    }

    #[Test]
    public function GetPEEReturnsNonZeroForDifferentStrings(): void
    {
        $struct = $this->createStruct([
            'translation' => 'completely different text here',
            'suggestion' => 'Hello world',
            'target' => 'en-US',
        ]);

        $result = $struct->getPEE();
        $this->assertIsFloat($result);
        $this->assertGreaterThan(0.0, $result);
    }

    #[Test]
    public function GetPEEBwtTranslationSuggestionReturnsZeroWhenLastTranslationEmpty(): void
    {
        $struct = $this->createStruct([
            'last_translation' => '',
            'suggestion' => 'some suggestion',
        ]);

        $this->assertSame(0.0, $struct->getPEEBwtTranslationSuggestion());
    }

    #[Test]
    public function GetPEEBwtTranslationSuggestionReturnsZeroWhenSuggestionNull(): void
    {
        $struct = $this->createStruct([
            'last_translation' => 'some text',
            'suggestion' => null,
        ]);

        $this->assertSame(0.0, $struct->getPEEBwtTranslationSuggestion());
    }

    #[Test]
    public function GetPEEBwtTranslationSuggestionReturnsFloat(): void
    {
        $struct = $this->createStruct([
            'last_translation' => 'Hello world',
            'suggestion' => 'Hello world',
            'target' => 'en-US',
        ]);

        $this->assertSame(0.0, $struct->getPEEBwtTranslationSuggestion());
    }

    #[Test]
    public function GetPEEBwtTranslationReviseReturnsZeroWhenLastTranslationEmpty(): void
    {
        $struct = $this->createStruct([
            'last_translation' => '',
            'last_revisions' => [['translation' => 'rev', 'source_page' => 1]],
        ]);

        $this->assertSame(0.0, $struct->getPEEBwtTranslationRevise());
    }

    #[Test]
    public function GetPEEBwtTranslationReviseReturnsZeroWhenNoRevisions(): void
    {
        $struct = $this->createStruct([
            'last_translation' => 'some text',
            'last_revisions' => [],
        ]);

        $this->assertSame(0.0, $struct->getPEEBwtTranslationRevise());
    }

    #[Test]
    public function GetPEEBwtTranslationReviseReturnsFloat(): void
    {
        $struct = $this->createStruct([
            'last_translation' => 'Hello world',
            'last_revisions' => [
                ['translation' => 'Hello world', 'source_page' => 1],
            ],
            'target' => 'en-US',
        ]);

        $this->assertSame(0.0, $struct->getPEEBwtTranslationRevise());
    }

    #[Test]
    public function GetPEEBwtTranslationReviseUsesLastRevision(): void
    {
        $struct = $this->createStruct([
            'last_translation' => 'original text here',
            'last_revisions' => [
                ['translation' => 'first revision', 'source_page' => 1],
                ['translation' => 'completely changed revision', 'source_page' => 2],
            ],
            'target' => 'en-US',
        ]);

        $result = $struct->getPEEBwtTranslationRevise();
        $this->assertIsFloat($result);
        $this->assertGreaterThan(0.0, $result);
    }

    #[Test]
    public function GetTmAnalysisStatus(): void
    {
        $struct = $this->createStruct();
        $reflection = new \ReflectionProperty(QualityReportSegmentStruct::class, 'tm_analysis_status');
        $reflection->setValue($struct, 'DONE');

        $this->assertSame('DONE', $struct->getTmAnalysisStatus());
    }

    #[Test]
    public function GetLocalWarningReturnsEmptyWhenTranslationNull(): void
    {
        $struct = $this->createStruct(['translation' => null]);

        $featureSet = $this->createStub(\Model\FeaturesBase\FeatureSet::class);
        $chunk = new \Model\Jobs\JobStruct();
        $chunk->id = 1;
        $chunk->password = 'abc123';
        $chunk->source = 'en-US';
        $chunk->target = 'it-IT';

        $this->assertSame([], $struct->getLocalWarning($featureSet, $chunk));
    }

    #[Test]
    public function GetLocalWarningReturnsEmptyWhenChunkIdNull(): void
    {
        $struct = $this->createStruct(['translation' => 'some text']);

        $featureSet = $this->createStub(\Model\FeaturesBase\FeatureSet::class);
        $chunk = new \Model\Jobs\JobStruct();
        $chunk->id = null;
        $chunk->password = 'abc123';
        $chunk->source = 'en-US';
        $chunk->target = 'it-IT';

        $this->assertSame([], $struct->getLocalWarning($featureSet, $chunk));
    }

    #[Test]
    public function GetLocalWarningReturnsEmptyWhenChunkPasswordNull(): void
    {
        $struct = $this->createStruct(['translation' => 'some text']);

        $featureSet = $this->createStub(\Model\FeaturesBase\FeatureSet::class);
        $chunk = new \Model\Jobs\JobStruct();
        $chunk->id = 1;
        $chunk->password = null;
        $chunk->source = 'en-US';
        $chunk->target = 'it-IT';

        $this->assertSame([], $struct->getLocalWarning($featureSet, $chunk));
    }

    #[Test]
    public function GetLocalWarningExecutesFullPathWithInjectedMetadataDao(): void
    {
        $metadataDao = $this->createStub(MetadataDao::class);
        $metadataDao->method('getSubfilteringCustomHandlers')->willReturn(null);

        $struct = new QualityReportSegmentStruct([], $metadataDao);
        $struct->sid = 1;
        $struct->segment = 'Hello world';
        $struct->translation = 'Ciao mondo';
        $struct->target = 'it-IT';

        $featureSet = new \Model\FeaturesBase\FeatureSet();
        $chunk = new \Model\Jobs\JobStruct();
        $chunk->id = 1;
        $chunk->id_project = 1;
        $chunk->password = 'abc123';
        $chunk->source = 'en-US';
        $chunk->target = 'it-IT';

        $result = $struct->getLocalWarning($featureSet, $chunk);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('details', $result);
        $this->assertArrayHasKey('total', $result);
    }
}
