<?php

declare(strict_types=1);

namespace Tests\Unit\Features\Hook;

use Model\ActivityLog\ActivityLogStruct;
use Model\FeaturesBase\Hook\Event\Filter\AnalysisBeforeMTGetContributionEvent;
use Model\FeaturesBase\Hook\Event\Filter\AppendFieldToAnalysisObjectEvent;
use Model\FeaturesBase\Hook\Event\Filter\AppendInitialTemplateVarsEvent;
use Model\FeaturesBase\Hook\Event\Filter\CharacterLengthCountEvent;
use Model\FeaturesBase\Hook\Event\Filter\CheckTagMismatchEvent;
use Model\FeaturesBase\Hook\Event\Filter\CheckTagPositionsEvent;
use Model\FeaturesBase\Hook\Event\Filter\CorrectTagErrorsEvent;
use Model\FeaturesBase\Hook\Event\Filter\DecodeInstructionsEvent;
use Model\FeaturesBase\Hook\Event\Filter\EncodeInstructionsEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterActivityLogEntryEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterContributionStructOnMTSetEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterContributionStructOnSetTranslationEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterCreateProjectFeaturesEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterGetSegmentsResultEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterJobPasswordToReviewPasswordEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterMyMemoryGetParametersEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterPayableRatesEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterRevisionChangeNotificationListEvent;
use Model\FeaturesBase\Hook\Event\Filter\FromLayer0ToLayer1Event;
use Model\FeaturesBase\Hook\Event\Filter\HandleJsonNotesBeforeInsertEvent;
use Model\FeaturesBase\Hook\Event\Filter\InjectExcludedTagsInQaEvent;
use Model\FeaturesBase\Hook\Event\Filter\IsAnInternalUserEvent;
use Model\FeaturesBase\Hook\Event\Filter\OutsourceAvailableInfoEvent;
use Model\FeaturesBase\Hook\Event\Filter\PopulatePreTranslationsEvent;
use Model\FeaturesBase\Hook\Event\Filter\PrepareNotesForRenderingEvent;
use Model\FeaturesBase\Hook\Event\Filter\ProjectUrlsEvent;
use Model\FeaturesBase\Hook\Event\Filter\RewriteContributionContextsEvent;
use Model\FeaturesBase\Hook\Event\Filter\SanitizeOriginalDataMapEvent;
use Model\FeaturesBase\Hook\Event\Filter\WordCountEvent;
use Model\FeaturesBase\Hook\FilterEvent;
use Model\Jobs\JobStruct;
use Model\ProjectCreation\ProjectStructure;
use Model\Projects\ProjectStruct;
use Matecat\SubFiltering\Commons\Pipeline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use ReflectionClass;

class FilterEventSubclassTest extends AbstractTest
{
    #[Test]
    #[DataProvider('hookNameProvider')]
    public function allFilterEventsReturnCorrectHookName(string $class, string $expectedHookName): void
    {
        self::assertSame($expectedHookName, $class::hookName());
    }

    #[Test]
    #[DataProvider('hookNameProvider')]
    public function allFilterEventsAreFinalAndExtendFilterEvent(string $class, string $_hookName): void
    {
        $reflection = new ReflectionClass($class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isSubclassOf(FilterEvent::class));
    }

    public static function hookNameProvider(): array
    {
        return [
            'AnalysisBeforeMTGetContribution' => [AnalysisBeforeMTGetContributionEvent::class, 'analysisBeforeMTGetContribution'],
            'AppendFieldToAnalysisObject' => [AppendFieldToAnalysisObjectEvent::class, 'appendFieldToAnalysisObject'],
            'AppendInitialTemplateVars' => [AppendInitialTemplateVarsEvent::class, 'appendInitialTemplateVars'],
            'CharacterLengthCount' => [CharacterLengthCountEvent::class, 'characterLengthCount'],
            'CheckTagMismatch' => [CheckTagMismatchEvent::class, 'checkTagMismatch'],
            'CheckTagPositions' => [CheckTagPositionsEvent::class, 'checkTagPositions'],
            'CorrectTagErrors' => [CorrectTagErrorsEvent::class, 'correctTagErrors'],
            'DecodeInstructions' => [DecodeInstructionsEvent::class, 'decodeInstructions'],
            'EncodeInstructions' => [EncodeInstructionsEvent::class, 'encodeInstructions'],
            'FilterActivityLogEntry' => [FilterActivityLogEntryEvent::class, 'filterActivityLogEntry'],
            'FilterContributionStructOnMTSet' => [FilterContributionStructOnMTSetEvent::class, 'filterContributionStructOnMTSet'],
            'FilterContributionStructOnSetTranslation' => [FilterContributionStructOnSetTranslationEvent::class, 'filterContributionStructOnSetTranslation'],
            'FilterCreateProjectFeatures' => [FilterCreateProjectFeaturesEvent::class, 'filterCreateProjectFeatures'],
            'FilterGetSegmentsResult' => [FilterGetSegmentsResultEvent::class, 'filterGetSegmentsResult'],
            'FilterJobPasswordToReviewPassword' => [FilterJobPasswordToReviewPasswordEvent::class, 'filterJobPasswordToReviewPassword'],
            'FilterMyMemoryGetParameters' => [FilterMyMemoryGetParametersEvent::class, 'filterMyMemoryGetParameters'],
            'FilterPayableRates' => [FilterPayableRatesEvent::class, 'filterPayableRates'],
            'FilterRevisionChangeNotificationList' => [FilterRevisionChangeNotificationListEvent::class, 'filterRevisionChangeNotificationList'],
            'FromLayer0ToLayer1' => [FromLayer0ToLayer1Event::class, 'fromLayer0ToLayer1'],
            'HandleJsonNotesBeforeInsert' => [HandleJsonNotesBeforeInsertEvent::class, 'handleJsonNotesBeforeInsert'],
            'InjectExcludedTagsInQa' => [InjectExcludedTagsInQaEvent::class, 'injectExcludedTagsInQa'],
            'IsAnInternalUser' => [IsAnInternalUserEvent::class, 'isAnInternalUser'],
            'OutsourceAvailableInfo' => [OutsourceAvailableInfoEvent::class, 'outsourceAvailableInfo'],
            'PopulatePreTranslations' => [PopulatePreTranslationsEvent::class, 'populatePreTranslations'],
            'PrepareNotesForRendering' => [PrepareNotesForRenderingEvent::class, 'prepareNotesForRendering'],
            'ProjectUrls' => [ProjectUrlsEvent::class, 'projectUrls'],
            'RewriteContributionContexts' => [RewriteContributionContextsEvent::class, 'rewriteContributionContexts'],
            'SanitizeOriginalDataMap' => [SanitizeOriginalDataMapEvent::class, 'sanitizeOriginalDataMap'],
            'WordCount' => [WordCountEvent::class, 'wordCount'],
        ];
    }

    #[Test]
    public function filterRevisionChangeNotificationListEventGetSetEmails(): void
    {
        $emails = ['a@test.com', 'b@test.com'];
        $event = new FilterRevisionChangeNotificationListEvent($emails);

        self::assertSame($emails, $event->getEmails());

        $updated = ['c@test.com'];
        $event->setEmails($updated);
        self::assertSame($updated, $event->getEmails());
    }

    #[Test]
    public function filterActivityLogEntryEventGetSetRecord(): void
    {
        $record = new ActivityLogStruct();
        $record->id = 42;
        $event = new FilterActivityLogEntryEvent($record);

        self::assertSame($record, $event->getRecord());

        $newRecord = new ActivityLogStruct();
        $newRecord->id = 99;
        $event->setRecord($newRecord);
        self::assertSame($newRecord, $event->getRecord());
    }

    #[Test]
    public function wordCountEventGetSet(): void
    {
        $event = new WordCountEvent(150);

        self::assertSame(150, $event->getWordCount());

        $event->setWordCount(200);
        self::assertSame(200, $event->getWordCount());
    }

    #[Test]
    public function filterPayableRatesEventGettersAndSetter(): void
    {
        $rates = ['ICE' => 0, 'MT' => 100];
        $event = new FilterPayableRatesEvent($rates, 'en-US', 'it-IT');

        self::assertSame($rates, $event->getRates());
        self::assertSame('en-US', $event->getSourceLanguage());
        self::assertSame('it-IT', $event->getTargetLanguage());

        $newRates = ['ICE' => 10];
        $event->setRates($newRates);
        self::assertSame($newRates, $event->getRates());
    }

    #[Test]
    public function filterCreateProjectFeaturesEventGettersAndSetter(): void
    {
        $features = ['feature_a', 'feature_b'];
        $controller = new \stdClass();
        $event = new FilterCreateProjectFeaturesEvent($features, $controller);

        self::assertSame($features, $event->getProjectFeatures());
        self::assertSame($controller, $event->getController());

        $newFeatures = ['feature_c'];
        $event->setProjectFeatures($newFeatures);
        self::assertSame($newFeatures, $event->getProjectFeatures());
    }

    #[Test]
    public function isAnInternalUserEventGettersAndSetter(): void
    {
        $event = new IsAnInternalUserEvent('user@example.com');

        self::assertSame('user@example.com', $event->getEmail());
        self::assertFalse($event->isInternal());

        $event->setIsInternal(true);
        self::assertTrue($event->isInternal());
    }

    #[Test]
    public function filterJobPasswordToReviewPasswordEventGettersAndSetter(): void
    {
        $event = new FilterJobPasswordToReviewPasswordEvent('abc123', 42);

        self::assertSame('abc123', $event->getPassword());
        self::assertSame(42, $event->getIdJob());

        $event->setPassword('xyz789');
        self::assertSame('xyz789', $event->getPassword());
    }

    #[Test]
    public function outsourceAvailableInfoEventGettersAndSetter(): void
    {
        $event = new OutsourceAvailableInfoEvent(null, 'customer_1', 99);

        self::assertNull($event->getFilterable());
        self::assertSame('customer_1', $event->getIdCustomer());
        self::assertSame(99, $event->getIdJob());

        $event->setFilterable(['outsource' => true]);
        self::assertSame(['outsource' => true], $event->getFilterable());
    }

    #[Test]
    public function correctTagErrorsEventGettersAndSetter(): void
    {
        $event = new CorrectTagErrorsEvent('<g id="1">text</g>', ['map_key' => 'value']);

        self::assertSame('<g id="1">text</g>', $event->getSegment());
        self::assertSame(['map_key' => 'value'], $event->getOriginalDataMap());

        $event->setSegment('<g id="1">corrected</g>');
        self::assertSame('<g id="1">corrected</g>', $event->getSegment());
    }

    #[Test]
    public function sanitizeOriginalDataMapEventGetSet(): void
    {
        $map = ['key1' => 'val1'];
        $event = new SanitizeOriginalDataMapEvent($map);

        self::assertSame($map, $event->getOriginalDataMap());

        $newMap = ['key2' => 'val2'];
        $event->setOriginalDataMap($newMap);
        self::assertSame($newMap, $event->getOriginalDataMap());
    }

    #[Test]
    public function injectExcludedTagsInQaEventGetSet(): void
    {
        $tags = ['bx', 'ex'];
        $event = new InjectExcludedTagsInQaEvent($tags);

        self::assertSame($tags, $event->getExcludedTags());

        $event->setExcludedTags(['ph']);
        self::assertSame(['ph'], $event->getExcludedTags());
    }

    #[Test]
    public function prepareNotesForRenderingEventGetSet(): void
    {
        $notes = [['id' => 1, 'note' => 'test']];
        $event = new PrepareNotesForRenderingEvent($notes);

        self::assertSame($notes, $event->getNotes());

        $event->setNotes([]);
        self::assertSame([], $event->getNotes());
    }

    #[Test]
    public function appendInitialTemplateVarsEventGetSet(): void
    {
        $codes = ['var1' => 'val1'];
        $event = new AppendInitialTemplateVarsEvent($codes);

        self::assertSame($codes, $event->getCodes());

        $event->setCodes(['var2' => 'val2']);
        self::assertSame(['var2' => 'val2'], $event->getCodes());
    }

    #[Test]
    public function checkTagMismatchEventGettersAndSetter(): void
    {
        $qa = new \stdClass();
        $event = new CheckTagMismatchEvent(0, $qa);

        self::assertSame(0, $event->getErrorCode());
        self::assertSame($qa, $event->getQaInstance());

        $event->setErrorCode(2000);
        self::assertSame(2000, $event->getErrorCode());
    }

    #[Test]
    public function checkTagPositionsEventGettersAndSetter(): void
    {
        $qa = new \stdClass();
        $event = new CheckTagPositionsEvent(false, $qa);

        self::assertFalse($event->getErrorCode());
        self::assertSame($qa, $event->getQaInstance());

        $event->setErrorCode(true);
        self::assertTrue($event->getErrorCode());
    }

    #[Test]
    public function characterLengthCountEventGetSet(): void
    {
        $event = new CharacterLengthCountEvent(42);

        self::assertSame(42, $event->getFilterable());

        $event->setFilterable('string_value');
        self::assertSame('string_value', $event->getFilterable());
    }

    #[Test]
    public function populatePreTranslationsEventGetSet(): void
    {
        $event = new PopulatePreTranslationsEvent(false);

        self::assertFalse($event->getDefault());

        $event->setDefault(true);
        self::assertTrue($event->getDefault());
    }

    #[Test]
    public function decodeInstructionsEventGetSet(): void
    {
        $event = new DecodeInstructionsEvent('raw_value');

        self::assertSame('raw_value', $event->getValue());

        $event->setValue(['decoded' => true]);
        self::assertSame(['decoded' => true], $event->getValue());
    }

    #[Test]
    public function encodeInstructionsEventGetSet(): void
    {
        $event = new EncodeInstructionsEvent(['key' => 'value']);

        self::assertSame(['key' => 'value'], $event->getValue());

        $event->setValue('encoded_string');
        self::assertSame('encoded_string', $event->getValue());
    }

    #[Test]
    public function projectUrlsEventGetSet(): void
    {
        $formatted = ['translate_url' => 'http://example.com'];
        $event = new ProjectUrlsEvent($formatted);

        self::assertSame($formatted, $event->getFormatted());

        $event->setFormatted(null);
        self::assertNull($event->getFormatted());
    }

    #[Test]
    public function filterGetSegmentsResultEventGettersAndSetter(): void
    {
        $chunk = new JobStruct();
        $chunk->id = 10;
        $data = [['segment' => 'hello']];
        $event = new FilterGetSegmentsResultEvent($data, $chunk);

        self::assertSame($data, $event->getData());
        self::assertSame($chunk, $event->getChunk());

        $event->setData([]);
        self::assertSame([], $event->getData());
    }

    #[Test]
    public function filterMyMemoryGetParametersEventGettersAndSetter(): void
    {
        $params = ['de' => 'en-US'];
        $config = ['key' => 'abc'];
        $event = new FilterMyMemoryGetParametersEvent($params, $config);

        self::assertSame($params, $event->getParameters());
        self::assertSame($config, $event->getConfig());

        $event->setParameters(['de' => 'it-IT']);
        self::assertSame(['de' => 'it-IT'], $event->getParameters());
    }

    #[Test]
    public function analysisBeforeMTGetContributionEventGettersAndSetter(): void
    {
        $config = ['timeout' => 30];
        $mtEngine = new \stdClass();
        $queueElement = new \stdClass();
        $event = new AnalysisBeforeMTGetContributionEvent($config, $mtEngine, $queueElement);

        self::assertSame($config, $event->getConfig());
        self::assertSame($mtEngine, $event->getMtEngine());
        self::assertSame($queueElement, $event->getQueueElement());

        $event->setConfig(['timeout' => 60]);
        self::assertSame(['timeout' => 60], $event->getConfig());
    }

    #[Test]
    public function rewriteContributionContextsEventGettersAndSetter(): void
    {
        $segments = [['id' => 1]];
        $requestData = ['id_job' => 5];
        $event = new RewriteContributionContextsEvent($segments, $requestData);

        self::assertSame($segments, $event->getSegmentsList());
        self::assertSame($requestData, $event->getRequestData());

        $event->setSegmentsList(null);
        self::assertNull($event->getSegmentsList());
    }

    #[Test]
    public function filterContributionStructOnSetTranslationEventGettersAndSetter(): void
    {
        $contrib = new \stdClass();
        $project = new ProjectStruct();
        $segment = new \stdClass();
        $event = new FilterContributionStructOnSetTranslationEvent($contrib, $project, $segment);

        self::assertSame($contrib, $event->getContributionStruct());
        self::assertSame($project, $event->getProject());
        self::assertSame($segment, $event->getSegment());

        $newContrib = new \stdClass();
        $event->setContributionStruct($newContrib);
        self::assertSame($newContrib, $event->getContributionStruct());
    }

    #[Test]
    public function filterContributionStructOnMTSetEventGettersAndSetter(): void
    {
        $contrib = new \stdClass();
        $translation = new \stdClass();
        $segment = new \stdClass();
        $filter = new \stdClass();
        $event = new FilterContributionStructOnMTSetEvent($contrib, $translation, $segment, $filter);

        self::assertSame($contrib, $event->getContributionStruct());
        self::assertSame($translation, $event->getTranslation());
        self::assertSame($segment, $event->getSegment());
        self::assertSame($filter, $event->getFilter());

        $newContrib = ['key' => 'value'];
        $event->setContributionStruct($newContrib);
        self::assertSame($newContrib, $event->getContributionStruct());
    }

    #[Test]
    public function handleJsonNotesBeforeInsertEventGetSet(): void
    {
        $ps = new ProjectStructure([]);
        $event = new HandleJsonNotesBeforeInsertEvent($ps);

        self::assertSame($ps, $event->getProjectStructure());

        $ps2 = new ProjectStructure([]);
        $event->setProjectStructure($ps2);
        self::assertSame($ps2, $event->getProjectStructure());
    }

    #[Test]
    public function appendFieldToAnalysisObjectEventGettersAndSetter(): void
    {
        $metadata = ['key' => 'val'];
        $ps = new ProjectStructure([]);
        $event = new AppendFieldToAnalysisObjectEvent($metadata, $ps);

        self::assertSame($metadata, $event->getMetadata());
        self::assertSame($ps, $event->getProjectStructure());

        $event->setMetadata(['new_key' => 'new_val']);
        self::assertSame(['new_key' => 'new_val'], $event->getMetadata());
    }

    #[Test]
    public function fromLayer0ToLayer1EventGetSet(): void
    {
        $pipeline = new Pipeline('en-US', 'it-IT');
        $event = new FromLayer0ToLayer1Event($pipeline);

        self::assertSame($pipeline, $event->getChannel());

        $pipeline2 = new Pipeline('fr-FR', 'de-DE');
        $event->setChannel($pipeline2);
        self::assertSame($pipeline2, $event->getChannel());
    }
}
