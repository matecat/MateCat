<?php

namespace Utils\Validator\JSONSchema;

use RuntimeException;
use Swaggest\JsonSchema\RemoteRefProvider;
use Utils\Registry\AppConfig;

/**
 * Resolves a `$ref` inside a JSON schema to the schema it points to.
 *
 * The reference is read as a file path first, then as a file name under
 * `inc/validation/schema/`, and otherwise as the JSON schema itself. Example: a `$ref` of
 * `"project_template.json"` loads `inc/validation/schema/project_template.json`.
 */
class SchemaFileRefProvider implements RemoteRefProvider
{

    /**
     * @param string $url
     *
     * @return object
     * @throws RuntimeException
     */
    public function getSchemaData($url): object
    {
        if (is_file($url)) {
            $url = file_get_contents($url) ?: $url;
        } elseif (is_file(AppConfig::$ROOT . '/inc/validation/schema/' . $url)) {
            $url = file_get_contents(AppConfig::$ROOT . '/inc/validation/schema/' . $url) ?: $url;
        }

        return JSONValidator::getValidJSONSchema($url);
    }

}
