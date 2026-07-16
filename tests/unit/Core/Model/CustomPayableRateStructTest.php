<?php


namespace Matecat\Core\Model;

use DomainException;
use Exception;
use Matecat\Locales\Languages;
use Matecat\TestHelpers\AbstractTest;
use Model\Analysis\PayableRates;
use Model\PayableRates\CustomPayableRateStruct;
use PHPUnit\Framework\Attributes\Test;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/06/2017
 * Time: 16:51
 */
class CustomPayableRateStructTest extends AbstractTest
{

    /**
     * @test
     */
    #[Test]
    public function convertLanguageToIsoCode()
    {
        $languages = Languages::getInstance();
        $langs = [
            'es-419' => 'es',
            'es-ES' => 'es',
            'fr-FR' => 'fr',
            'fr-CA' => 'fr',
        ];

        foreach ($langs as $rfc3066 => $iso) {
            $isoCode = $languages->convertLanguageToIsoCode($rfc3066);


            $this->assertEquals($iso, $isoCode);
        }
    }

    /**
     * @test
     */
    #[Test]
    public function getPayableRates()
    {
        $model = new CustomPayableRateStruct();
        $model->id = 12;
        $model->name = 'test';
        $model->version = 2;

        $model->breakdowns = '
            {
                "default": {
                    "NO_MATCH": 80,
                    "50%-74%": 80,
                    "75%-84%": 80,
                    "85%-94%": 80,
                    "95%-99%": 80,
                    "100%": 80,
                    "100%_PUBLIC": 80,
                    "REPETITIONS": 80,
                    "INTERNAL": 80,
                    "ICE": 80,
                    "ICE_MT": 80,
                    "MT": 80
                },
                "en-AU": {
                    "fr-CA": {
                        "NO_MATCH": 70,
                        "50%-74%": 70,
                        "75%-84%": 70,
                        "85%-94%": 70,
                        "95%-99%": 70,
                        "100%": 70,
                        "100%_PUBLIC": 70,
                        "REPETITIONS": 70,
                        "INTERNAL": 70,
                        "MT": 70,
                        "ICE": 70
                    }
                },
                "en-US": {
                    "fr-CA": {
                        "NO_MATCH": 75,
                        "50%-74%": 75,
                        "75%-84%": 75,
                        "85%-94%": 75,
                        "95%-99%": 75,
                        "100%": 75,
                        "100%_PUBLIC": 75,
                        "REPETITIONS": 75,
                        "INTERNAL": 75,
                        "MT": 75,
                        "ICE": 75,
                        "ICE_MT": 75
                    }
                }
            }
        ';

        $languageCombos = [
            ['en-AU', 'fr-CA', 70],
            ['en-AU', 'fr-CA', 70],
            ['en-AU', 'fr-FR', 80],
            ['en-US', 'fr-CA', 75],
            ['en-US', 'fr', 80],
            ['it', 'fr', 80],
        ];

        foreach ($languageCombos as $languageCombo) {
            $payableRate = $model->getPayableRates($languageCombo[0], $languageCombo[1]);
            $errorMessage = 'Error for language combination ' . $languageCombo[0] . '<->' . $languageCombo[1] . '. Exp. ' . $languageCombo[2] . ', got ' . $payableRate['MT'];

            $this->assertEquals($languageCombo[2], $payableRate['MT'], $errorMessage);

            // NO ICE_MT set for en-AU -> fr-CA
            $errorMessage = 'Error for language combination ' . $languageCombo[0] . '<->' . $languageCombo[1] . '. Exp. ' . $languageCombo[2] . ', got ' . $payableRate['ICE_MT'] ?? "null";
            $this->assertEquals($languageCombo[2], $payableRate['ICE_MT'], $errorMessage);
        }
    }

    /** @return array<string,mixed> */
    private function validBreakdowns(): array
    {
        return ['default' => PayableRates::$DEFAULT_PAYABLE_RATES];
    }

    private function structWithBreakdowns(): CustomPayableRateStruct
    {
        $s = new CustomPayableRateStruct();
        $s->id = 5;
        $s->uid = 7;
        $s->version = 1;
        $s->name = 'rate';
        $s->breakdowns = $this->validBreakdowns();

        return $s;
    }

    #[Test]
    public function breakdownsToJson_serialises_the_breakdowns(): void
    {
        $json = $this->structWithBreakdowns()->breakdownsToJson();

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('default', $decoded);
    }

    #[Test]
    public function getBreakdownsArray_backfills_missing_ICE_MT_from_MT(): void
    {
        $s = new CustomPayableRateStruct();
        $s->breakdowns = json_encode([
            'default' => PayableRates::$DEFAULT_PAYABLE_RATES,
            'en-US'   => ['fr-FR' => ['MT' => 42]], // no ICE_MT -> must be backfilled to MT
        ]);

        $breakdowns = $s->getBreakdownsArray();

        $this->assertSame(42, $breakdowns['en-US']['fr-FR']['ICE_MT']);
        $this->assertSame(42, $breakdowns['en-US']['fr-FR']['MT']);
    }

    #[Test]
    public function hydrateFromJSON_populates_name_breakdowns_and_version(): void
    {
        $s = new CustomPayableRateStruct();
        $s->hydrateFromJSON((string)json_encode([
            'payable_rate_template_name' => 'hydrated',
            'version'                    => 3,
            'breakdowns'                 => [
                'default' => PayableRates::$DEFAULT_PAYABLE_RATES,
                // a real source->target node so validateBreakdowns iterates the inner loop too
                'en-US'   => ['fr-FR' => ['MT' => 80]],
            ],
        ]));

        $this->assertSame('hydrated', $s->name);
        $this->assertSame(3, $s->version);
        $this->assertArrayHasKey('default', (array)$s->breakdowns);
    }

    #[Test]
    public function hydrateFromJSON_throws_on_invalid_payload(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);

        (new CustomPayableRateStruct())->hydrateFromJSON((string)json_encode(['foo' => 'bar']));
    }

    #[Test]
    public function hydrateFromJSON_throws_when_default_node_missing(): void
    {
        $this->expectException(DomainException::class);

        (new CustomPayableRateStruct())->hydrateFromJSON((string)json_encode([
            'payable_rate_template_name' => 'no-default',
            'breakdowns'                 => ['en-US' => ['fr-FR' => ['MT' => 80]]],
        ]));
    }

    #[Test]
    public function hydrateFromJSON_throws_when_breakdowns_too_large(): void
    {
        $big = ['default' => PayableRates::$DEFAULT_PAYABLE_RATES];
        // pad past MAX_BREAKDOWN_SIZE (64kb) with junk language nodes
        for ($i = 0; $i < 4000; $i++) {
            $big["lang-$i"] = ['target' => ['MT' => 80]];
        }

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        (new CustomPayableRateStruct())->hydrateFromJSON((string)json_encode([
            'payable_rate_template_name' => 'too-big',
            'breakdowns'                 => $big,
        ]));
    }

    #[Test]
    public function hydrateFromJSON_throws_on_unsupported_language(): void
    {
        $this->expectException(DomainException::class);

        (new CustomPayableRateStruct())->hydrateFromJSON((string)json_encode([
            'payable_rate_template_name' => 'bad-lang',
            'breakdowns'                 => [
                'default'     => PayableRates::$DEFAULT_PAYABLE_RATES,
                'not-a-lang!' => ['fr-FR' => ['MT' => 80]],
            ],
        ]));
    }

    #[Test]
    public function getPayableRates_throws_on_unsupported_language(): void
    {
        $this->expectException(DomainException::class);

        $this->structWithBreakdowns()->getPayableRates('not-a-lang!', 'fr-FR');
    }

    #[Test]
    public function jsonSerialize_exposes_public_shape_with_formatted_dates(): void
    {
        $s = $this->structWithBreakdowns();
        $s->created_at = '2024-01-02 03:04:05';
        $s->modified_at = '2024-02-03 04:05:06';

        $out = $s->jsonSerialize();

        $this->assertSame(5, $out['id']);
        $this->assertSame(7, $out['uid']);
        $this->assertSame(1, $out['version']);
        $this->assertSame('rate', $out['payable_rate_template_name']);
        $this->assertArrayHasKey('default', $out['breakdowns']);
        $this->assertNotNull($out['createdAt']);
        $this->assertNotNull($out['modifiedAt']);
    }

    #[Test]
    public function jsonSerialize_returns_null_dates_when_unset(): void
    {
        $out = $this->structWithBreakdowns()->jsonSerialize();

        $this->assertNull($out['createdAt']);
        $this->assertNull($out['modifiedAt']);
    }
}