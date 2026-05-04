<?php

namespace Utils\Engines\Results;

use Model\FeaturesBase\FeatureSet;
use ReflectionClass;
use ReflectionProperty;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/03/15
 * Time: 19.02
 */
abstract class TMSAbstractResponse
{

    public int $responseStatus = 200;

    /**
     * @var string|array<string, mixed>
     */
    public string|array $responseDetails = "";

    /**
     * @var mixed
     */
    public mixed $responseData = [];
    public bool $mtLangSupported = true;

    /**
     * @var ErrorResponse|null
     */
    public ?ErrorResponse $error = null;

    protected string $_rawResponse = "";

    /**
     * @var ?FeatureSet
     */
    protected ?FeatureSet $featureSet = null;

    /**
     * @param array<string, mixed>|int|null $result
     * @param FeatureSet|null $featureSet
     * @param array<string, mixed>|null $dataRefMap
     * @param int|null $id_project
     *
     * @return static
     */
    public static function getInstance(mixed $result, ?FeatureSet $featureSet = null, ?array $dataRefMap = [], ?int $id_project = null): static
    {
        $class = get_called_class(); // late static binding

        /** @var static $instance */
        $instance = new $class($result, $dataRefMap, $id_project);

        if (is_array($result) && isset($result['responseStatus']) && $result['responseStatus'] >= 400) {
            $instance->error = new ErrorResponse($result['error'] ?? $result['responseDetails']);
        }

        if ($featureSet !== null) {
            $instance->featureSet($featureSet);
        }

        return $instance;
    }

    public function featureSet(FeatureSet $featureSet): void
    {
        $this->featureSet = $featureSet;
    }

    /**
     * Returns an array of the public attributes of the struct.
     * If $mask is provided, the resulting array will include
     * only the specified keys.
     *
     * This method is useful in conjunction with PDO execute, where only
     * a subset of the attributes may be required to be bound to the query.
     *
     * @param array<string>|null $mask a mask for the keys to return
     *
     * @return array<string, mixed>
     */
    public function toArray(?array $mask = []): array
    {
        $attributes = [];
        $reflectionClass = new ReflectionClass($this);
        $publicProperties = $reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($publicProperties as $property) {
            if (!empty($mask)) {
                if (!in_array($property->getName(), $mask)) {
                    continue;
                }
            }
            $attributes[$property->getName()] = $property->getValue($this);
        }

        return $attributes;
    }

} 