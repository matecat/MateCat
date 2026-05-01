<?php

namespace Model\PayableRates;

use DomainException;
use Exception;
use JsonSerializable;
use Matecat\Locales\Languages;
use Model\Analysis\PayableRates;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use TypeError;
use Utils\Date\DateTimeUtil;

class CustomPayableRateStruct extends AbstractDaoSilentStruct implements IDaoStruct, JsonSerializable
{
    const int MAX_BREAKDOWN_SIZE = 65535;

    public ?int $id = null;
    public ?int $uid = null;
    public int $version;
    public string $name;
    /**
     * @var array<string, array<string, array<string, int>>>|string
     */
    public string|array $breakdowns;
    public ?string $created_at = null;
    public ?string $modified_at = null;
    public ?string $deleted_at = null;

    /**
     * @return string
     * @throws TypeError
     */
    public function breakdownsToJson(): string
    {
        return json_encode($this->getBreakdownsArray());
    }

    /**
     * @return array<string, array<string, array<string, int>>>
     * @throws TypeError
     */
    public function getBreakdownsArray(): array
    {
        $this->breakdowns = (is_string($this->breakdowns) ? json_decode($this->breakdowns, true) : $this->breakdowns);

        // WARNING: backward compatibility for old data stored, they could not have ICE_MT
        foreach ($this->breakdowns as $sourceLang => $targetLanguages) {
            if ($sourceLang == 'default') {
                continue;
            }
            foreach ($targetLanguages as $targetLanguage => $targetPayableRates) {
                if (!isset($targetPayableRates['ICE_MT'])) {
                    $this->breakdowns[$sourceLang][$targetLanguage]['ICE_MT'] = $targetPayableRates['MT'];
                }
            }
        }

        return $this->breakdowns;
    }

    /**
     * @param string $source
     * @param string $target
     *
     * @return array<string, int>
     * @throws DomainException
     * @throws TypeError
     */
    public function getPayableRates(string $source, string $target): array
    {
        $breakdowns = $this->getBreakdownsArray();

        $this->validateLanguage($source);
        $this->validateLanguage($target);

        return PayableRates::resolveBreakdowns($breakdowns, $source, $target, $breakdowns['default']);
    }

    /**
     * @param string $json
     *
     * @return $this
     *
     * @throws Exception
     * @throws TypeError
     */
    public function hydrateFromJSON(string $json): CustomPayableRateStruct
    {
        $json = json_decode($json, true);

        if (
            !isset($json['payable_rate_template_name']) and
            !isset($json['breakdowns'])
        ) {
            throw new Exception("Cannot instantiate a new CustomPayableRateStruct. Invalid JSON provided.", 403);
        }

        $this->validateBreakdowns($json['breakdowns']);

        if (isset($json['version'])) {
            $this->version = $json['version'];
        }

        $this->name = $json['payable_rate_template_name'];
        $this->breakdowns = $json['breakdowns'];

        return $this;
    }

    /**
     * @param array<string, mixed> $breakdowns
     *
     * @throws Exception
     */
    private function validateBreakdowns(array $breakdowns): void
    {
        $size = mb_strlen(json_encode($breakdowns, JSON_NUMERIC_CHECK), '8bit');

        if ($size > self::MAX_BREAKDOWN_SIZE) {
            throw new Exception('`breakdowns` string is too large. Max size: 64kb', 400);
        }

        if (!isset($breakdowns['default'])) {
            throw new DomainException('`default` node is MANDATORY in the breakdowns array.', 403);
        }

        unset($breakdowns['default']);

        foreach ($breakdowns as $language => $breakdown) {
            $this->validateLanguage($language);

            foreach ($breakdown as $targetLanguage => $_rates) {
                $this->validateLanguage($targetLanguage);
            }
        }
    }

    /**
     * @param $lang
     * @throws DomainException
     */
    private function validateLanguage($lang): void
    {
        // rfc3066code --->  es-ES
        // isocode     --->  es
        $languages = Languages::getInstance();
        if (!$languages->isValidLanguage($lang)) {
            throw new DomainException($lang . ' is not a supported language', 403);
        }
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     * @throws TypeError
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => (int)$this->id,
            'uid' => (int)$this->uid,
            'version' => $this->version,
            'payable_rate_template_name' => $this->name,
            'breakdowns' => $this->getBreakdownsArray(),
            'createdAt' => $this->created_at !== null ? DateTimeUtil::formatIsoDate($this->created_at) : null,
            'modifiedAt' => $this->modified_at !== null ? DateTimeUtil::formatIsoDate($this->modified_at) : null,
        ];
    }
}
