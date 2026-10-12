<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 16/04/25
 * Time: 18:47
 *
 */

namespace Model\MTQE\Templates;

use DomainException;
use InvalidArgumentException;
use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use TypeError;
use Utils\Validation\UserSuppliedName;

class MTQEWorkflowTemplateStruct extends AbstractDaoSilentStruct implements JsonSerializable
{

    public int $id = 0;
    public string $name = "";
    public int $uid = 0;
    public ?string $created_at = null;
    public ?string $modified_at = null;
    public ?string $deleted_at = null;

    /**
     * @var MTQEWorkflowParams|null
     */
    public ?MTQEWorkflowParams $params = null;

    /**
     * @param string $json
     * @param null $uid
     *
     * @return $this
     * @throws DomainException
     * @throws TypeError
     * @throws InvalidArgumentException when the name is empty, holds a character the connection
     *                                  cannot carry, or will not fit the column
     */
    public function hydrateFromJSON(string $json, $uid = null): MTQEWorkflowTemplateStruct
    {
        $decoded_json = json_decode($json, true);

        if (!isset($decoded_json['name'])) {
            throw new DomainException("Cannot instantiate a new MTQEWorkflowTemplateStruct. Invalid data provided.", 400);
        }

        if (empty($uid) && empty($decoded_json['uid'])) {
            throw new DomainException("Cannot instantiate a new MTQEWorkflowTemplateStruct. Invalid user id provided.", 400);
        }

        $this->uid = $decoded_json['uid'] ?? $uid;
        // Normalised like every other template name. The column here is a latin1 varchar(255),
        // so a name in a non-Latin-1 script still cannot be stored — that is a separate schema
        // problem, not a reason to leave a control character or a decomposed form in the value.
        $this->name = UserSuppliedName::validated(
            $decoded_json['name'],
            'name',
            UserSuppliedName::TEMPLATE_NAME_MAX_LENGTH
        );

        if (isset($decoded_json['id'])) {
            $this->id = $decoded_json['id'];
        }

        if (isset($decoded_json['created_at'])) {
            $this->created_at = $decoded_json['created_at'];
        }

        if (isset($decoded_json['deleted_at'])) {
            $this->deleted_at = $decoded_json['deleted_at'];
        }

        if (isset($decoded_json['modified_at'])) {
            $this->modified_at = $decoded_json['modified_at'];
        }

        // params
        if (isset($decoded_json['params'])) {
            (is_string($decoded_json['params'])) ? $this->hydrateParamsFromJson($decoded_json['params']) : $this->hydrateParamsFromDataArray($decoded_json['params']);
        }

        return $this;
    }

    /**
     * @param string $jsonParams
     *
     * @return MTQEWorkflowTemplateStruct
     */
    public function hydrateParamsFromJson(string $jsonParams): MTQEWorkflowTemplateStruct
    {
        $rules = json_decode($jsonParams, true);

        return $this->hydrateParamsFromDataArray($rules);
    }

    /** @param array<string, mixed> $params */
    public function hydrateParamsFromDataArray(array $params): MTQEWorkflowTemplateStruct
    {
        $this->params = new MTQEWorkflowParams();

        // rules
        if (isset($params['params'])) {
            $this->params = new MTQEWorkflowParams($params['params']);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->getArrayCopy();
    }

    public function __toString(): string
    {
        return json_encode($this->jsonSerialize()) ?: '';
    }

}