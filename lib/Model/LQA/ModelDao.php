<?php

namespace Model\LQA;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use PDOException;
use ReflectionException;
use TypeError;

class ModelDao extends AbstractDao
{
    const string TABLE = "qa_models";

    protected static array $auto_increment_field = ['id'];

    protected static string $_sql_get_model_by_id = "SELECT * FROM qa_models WHERE id = :id LIMIT 1";

    /**
     * @param int $id
     * @param int $ttl
     *
     * @return ModelStruct|null
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public static function findById(int $id, int $ttl = 0): ?ModelStruct
    {
        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(self::$_sql_get_model_by_id);

        /** @var list<ModelStruct> $result */
        $result = $thisDao->setCacheTTL($ttl)->_fetchObjectMap($stmt, ModelStruct::class, ['id' => $id]);

        return $result[0] ?? null;
    }

    /**
     * @param array{
     *     uid: int,
     *     label?: string|null,
     *     passfail: array{type: string, options: array{limit?: list<int|string>}},
     *     id_template?: int|null,
     *     version?: string|int,
     *     categories?: list<array<string, mixed>>,
     *     severities?: list<array{penalty: int|string}>
     * } $data
     *
     * @return ModelStruct
     * @throws PDOException
     * @throws TypeError
     */
    public static function createRecord(array $data): ModelStruct
    {
        $model_hash = static::_getModelHash($data);

        $sql = "INSERT INTO qa_models ( uid, label, pass_type, pass_options, `hash`, `qa_model_template_id` ) " .
            " VALUES ( :uid, :label, :pass_type, :pass_options, :hash, :qa_model_template_id ) ";

        $struct = new ModelStruct([
            'uid' => $data['uid'],
            'label' => $data['label'] ?? null,
            'pass_type' => $data['passfail']['type'],
            'pass_options' => self::decodePassOptions($data['passfail']['options']),
            'hash' => $model_hash,
            'qa_model_template_id' => (isset($data['id_template'])) ? $data['id_template'] : null,
        ]);

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare($sql);
        $stmt->execute(
            $struct->toArray(
                ['uid', 'label', 'pass_type', 'pass_options', 'hash', 'qa_model_template_id']
            )
        );

        $struct->id = (int)$conn->lastInsertId();

        return $struct;
    }

    /**
     * Return ALWAYS a structure like this: {"limit":[15,10]}
     *
     * @param array{limit?: list<int|string>} $options
     *
     * @return false|string
     */
    private static function decodePassOptions(array $options): false|string
    {
        if (!isset($options['limit'])) {
            return json_encode([]);
        }

        $limits = [];

        foreach ($options['limit'] as $limit) {
            $limits[] = (int)$limit;
        }

        $options['limit'] = $limits;

        return json_encode($options);
    }

    /**
     * @param array{
     *     uid?: int,
     *     label?: string|null,
     *     version?: string|int,
     *     categories?: list<array<string, mixed>>,
     *     severities?: list<array{penalty: int|string}>,
     *     passfail: array{type: string, options: array{limit?: list<int|string>}}
     * } $model_root
     */
    protected static function _getModelHash(array $model_root): int
    {
        $h_string = '';

        $h_string .= $model_root['version'] ?? '';

        foreach ($model_root['categories'] ?? [] as $category) {
            $h_string .= $category['code'];
        }

        if (isset($model_root['severities'])) {
            foreach ($model_root['severities'] as $severity) {
                $h_string .= $severity['penalty'];
            }
        }

        $h_string .= $model_root['passfail']['type'] . implode("", $model_root['passfail']['options']['limit'] ?? []);

        return crc32($h_string);
    }

    /**
     * Recursively create categories and subcategories based on the
     * QA model definition.
     *
     * @param array{model: array{
     *     uid: int,
     *     label?: string|null,
     *     version: string|int,
     *     passfail: array{type: string, options: array{limit?: list<int|string>}},
     *     categories: list<array<string, mixed>>,
     *     severities?: list<array{penalty: int|string}>,
     *     id_template?: int|null
     * }} $json
     *
     * @return ModelStruct
     * @throws PDOException
     * @throws ReflectionException
     * @throws TypeError
     */
    public static function createModelFromJsonDefinition(array $json): ModelStruct
    {
        $model_root = $json['model'];
        $model = ModelDao::createRecord($model_root);

        $default_severities = $model_root['severities'] ?? [];
        $categories = $model_root['categories'];

        foreach ($categories as $category) {
            self::insertCategory($category, $model->id, $default_severities, null);
        }

        return $model;
    }

    /**
     * @param array{label: string, severities?: list<mixed>, subcategories?: list<array<string, mixed>>}|array<string, mixed> $category
     * @param list<mixed> $default_severities
     * @throws PDOException
     * @throws ReflectionException
     * @throws TypeError
     */
    private static function insertCategory(array $category, int $model_id, array $default_severities, ?int $parent_id): void
    {
        if (!array_key_exists('severities', $category)) {
            $category['severities'] = $default_severities;
        }

        /*
         * Any other key found in the JSON array will populate the `options` field
         */
        $options = [];

        foreach (array_keys($category) as $key) {
            if (!in_array($key, ['label', 'severities', 'subcategories'])) {
                $options[$key] = $category[$key];
            }
        }

        $category_record = CategoryDao::createRecord([
            'id_model' => $model_id,
            'label' => $category['label'],
            'options' => (empty($options) ? null : json_encode($options)),
            'id_parent' => $parent_id,
            'severities' => json_encode($category['severities'])
        ]);

        if (array_key_exists('subcategories', $category) && !empty($category['subcategories'])) {
            foreach ($category['subcategories'] as $sub) {
                self::insertCategory($sub, $model_id, $default_severities, $category_record->id);
            }
        }
    }
}
