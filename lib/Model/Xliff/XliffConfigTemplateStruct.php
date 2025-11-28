<?php

namespace Model\Xliff;

use DomainException;
use Exception;
use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\Xliff\DTO\Xliff12Rule;
use Model\Xliff\DTO\Xliff20Rule;
use Model\Xliff\DTO\XliffRulesModel;
use stdClass;
use Utils\Date\DateTimeUtil;

class XliffConfigTemplateStruct extends AbstractDaoSilentStruct implements JsonSerializable
{

    public int     $id          = 0;
    public string  $name        = "";
    public int     $uid         = 0;
    public ?string $created_at  = null;
    public ?string $modified_at = null;
    public ?string $deleted_at  = null;

    /**
     * @var XliffRulesModel|null
     */
    public ?XliffRulesModel $rules = null;

    /**
     * @param string   $json
     * @param int|null $uid
     *
     * @return $this
     * @throws Exception
     */
    public function hydrateFromJSON(string $json, ?int $uid = null): XliffConfigTemplateStruct
    {
        $decoded_json = json_decode($json, true);

        if (!isset($decoded_json[ 'name' ])) {
            throw new DomainException("Cannot instantiate a new XliffConfigStruct. Invalid data provided.", 400);
        }

        if (empty($uid) && empty($decoded_json[ 'uid' ])) {
            throw new DomainException("Cannot instantiate a new XliffConfigStruct. Invalid user id provided.", 400);
        }

        $this->uid  = $decoded_json[ 'uid' ] ?? $uid;
        $this->name = $decoded_json[ 'name' ];

        if (isset($decoded_json[ 'id' ])) {
            $this->id = $decoded_json[ 'id' ];
        }

        if (isset($decoded_json[ 'created_at' ])) {
            $this->created_at = $decoded_json[ 'created_at' ];
        }

        if (isset($decoded_json[ 'deleted_at' ])) {
            $this->deleted_at = $decoded_json[ 'deleted_at' ];
        }

        if (isset($decoded_json[ 'modified_at' ])) {
            $this->modified_at = $decoded_json[ 'modified_at' ];
        }

        // rules
        if (isset($decoded_json[ 'rules' ])) {
            (is_string($decoded_json[ 'rules' ])) ? $this->hydrateRulesFromJson($decoded_json[ 'rules' ]) : $this->hydrateRulesFromDataArray($decoded_json[ 'rules' ]);
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    protected function hydrateRulesFromDataArray(array $rules): XliffConfigTemplateStruct
    {
        $this->rules = new XliffRulesModel();

        // rules
        if (isset($rules[ XliffRulesModel::XLIFF_12 ]) and is_array($rules[ XliffRulesModel::XLIFF_12 ])) {
            foreach ($rules[ XliffRulesModel::XLIFF_12 ] as $xliff12Rule) {
                $rule = new Xliff12Rule($xliff12Rule[ 'states' ], $xliff12Rule[ 'analysis' ], $xliff12Rule[ 'editor' ] ?? null, $xliff12Rule[ 'match_category' ] ?? null);
                $this->rules->addRule($rule);
            }
        }

        // xliff20
        if (isset($rules[ XliffRulesModel::XLIFF_20 ]) and is_array($rules[ XliffRulesModel::XLIFF_20 ])) {
            foreach ($rules[ XliffRulesModel::XLIFF_20 ] as $xliff20Rule) {
                $rule = new Xliff20Rule($xliff20Rule[ 'states' ], $xliff20Rule[ 'analysis' ], $xliff20Rule[ 'editor' ] ?? null, $xliff20Rule[ 'match_category' ] ?? null);
                $this->rules->addRule($rule);
            }
        }

        return $this;
    }

    /**
     * @param string $jsonRules
     *
     * @return XliffConfigTemplateStruct
     * @throws Exception
     */
    public function hydrateRulesFromJson(string $jsonRules): XliffConfigTemplateStruct
    {
        $rules = json_decode($jsonRules, true);

        return $this->hydrateRulesFromDataArray($rules);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function jsonSerialize(): array
    {
        return [
                'id'          => $this->id,
                'uid'         => $this->uid,
                'name'        => $this->name,
                'rules'       => $this->rules ?? new stdClass(),
                'created_at'  => DateTimeUtil::formatIsoDate($this->created_at),
                'modified_at' => DateTimeUtil::formatIsoDate($this->modified_at)
        ];
    }
}
