<?php

declare(strict_types=1);

namespace unit\Structs;

use Model\ActivityLog\ActivityLogStruct;
use Model\LQA\ModelStruct;
use Model\PayableRates\CustomPayableRateStruct;
use Model\QualityReport\QualityReportSegmentStruct;
use Model\Segments\ContextStruct;
use Model\Segments\SegmentOriginalDataStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\TmKeyManagement\TmKeyStruct;

class IterableValueTypesTest extends AbstractTest
{
    #[Test]
    public function qualityReportSegmentIterablePropertiesMatchExpectedShapes(): void
    {
        $struct = new QualityReportSegmentStruct();

        $struct->warnings = [['warning' => 'x']];
        $struct->parsed_time_to_edit = ['00:00:01', '0', 'a', 1];
        $struct->comments = [['comment' => 'x']];
        $struct->issues = [['issue' => 'x']];
        $struct->last_revisions = [['translation' => 'rev', 'source_page' => 1]];
        $struct->dataRefMap = ['id_1' => 'value_1'];

        self::assertIsArray($struct->warnings);
        self::assertIsArray($struct->parsed_time_to_edit);
        self::assertIsArray($struct->comments);
        self::assertIsArray($struct->issues);
        self::assertIsArray($struct->last_revisions);
        self::assertIsArray($struct->dataRefMap);
        self::assertIsString($struct->last_revisions[0]['translation']);
        self::assertIsInt($struct->last_revisions[0]['source_page']);
        self::assertIsString($struct->dataRefMap['id_1']);
    }

    #[Test]
    public function customPayableRateMethodsReturnExpectedIterableShapes(): void
    {
        $struct = new CustomPayableRateStruct();
        $struct->breakdowns = [
            'default' => ['NO_MATCH' => ['NO_MATCH' => 100, 'ICE_MT' => 50, 'MT' => 80]],
            'en-US' => ['it-IT' => ['NO_MATCH' => 100, 'ICE_MT' => 50, 'MT' => 80]],
        ];

        $breakdowns = $struct->getBreakdownsArray();

        self::assertIsArray($breakdowns);
        self::assertArrayHasKey('default', $breakdowns);
        self::assertIsInt($breakdowns['en-US']['it-IT']['NO_MATCH']);
    }

    #[Test]
    public function segmentOriginalDataMapRoundTripIsStringToStringArray(): void
    {
        $struct = new SegmentOriginalDataStruct();

        $map = ['mrk_1' => '<b>text</b>', 'mrk_2' => '<i>text</i>'];
        $struct->setMap($map);

        $returnedMap = $struct->getMap();

        self::assertSame($map, $returnedMap);
        self::assertIsString($returnedMap['mrk_1']);
        self::assertIsString($returnedMap['mrk_2']);
    }

    #[Test]
    public function contextJsonCanContainDecodedArrayData(): void
    {
        $struct = new ContextStruct([], false);
        $struct->context_json = ['k' => 'v', 'n' => 1];

        self::assertIsArray($struct->context_json);
        self::assertSame('v', $struct->context_json['k']);
        self::assertSame(1, $struct->context_json['n']);
    }

    #[Test]
    public function tmKeyStructToArrayAndJsonSerializeReturnAssociativeArrays(): void
    {
        $struct = new TmKeyStruct(['key' => 'abcde12345', 'name' => 'k']);

        $toArray = $struct->toArray();
        $serialized = $struct->jsonSerialize();

        self::assertIsArray($toArray);
        self::assertIsArray($serialized);
        self::assertArrayHasKey('key', $toArray);
        self::assertArrayHasKey('key', $serialized);
    }

    #[Test]
    public function modelStructLimitNormalizationReturnsListOfInts(): void
    {
        $model = new ModelStruct();
        $model->pass_options = json_encode(['limit' => ['1' => '8', '2' => '5']]);

        $limits = $model->getLimit();

        self::assertIsArray($limits);
        self::assertSame([8, 5], $limits);
    }

    #[Test]
    public function activityLogActionMapAndCacheShapeAssumptionsHold(): void
    {
        $action = ActivityLogStruct::getAction(ActivityLogStruct::DOWNLOAD_EDIT_LOG);

        self::assertIsString($action);
    }
}
