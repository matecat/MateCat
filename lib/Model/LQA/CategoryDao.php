<?php

namespace Model\LQA;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use PDO;
use PDOException;
use ReflectionException;
use TypeError;

class CategoryDao extends AbstractDao
{
    const string TABLE = 'qa_categories';

    /**
     * @param int $id
     *
     * @return CategoryStruct|null
     * @throws PDOException
     * @throws ReflectionException
     */
    public static function findById(int $id): ?CategoryStruct
    {
        /** @var ?CategoryStruct $res */
        $res = (new self())->fetchById($id, CategoryStruct::class);

        return $res;
    }

    /**
     * @param int $id_model
     * @param int|null $id_parent
     *
     * @return list<CategoryStruct>
     * @throws PDOException
     */
    public function findByIdModelAndIdParent(int $id_model, ?int $id_parent): array
    {
        $sql = "SELECT * FROM qa_categories WHERE id_model = :id_model AND id_parent = :id_parent ";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id_model' => $id_model, 'id_parent' => $id_parent]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, CategoryStruct::class);

        return array_values($stmt->fetchAll());
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return CategoryStruct
     * @throws PDOException
     * @throws TypeError
     */
    public static function createRecord(array $data): CategoryStruct
    {
        $categoryStruct = new CategoryStruct($data);

        $sql = "INSERT INTO qa_categories " .
            " ( id_model, label, id_parent, severities, options ) " .
            " VALUES " .
            " ( :id_model, :label, :id_parent, :severities, :options )";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(
            $categoryStruct->toArray(
                [
                    'id_model',
                    'label',
                    'id_parent',
                    'options',
                    'severities',
                ]
            )
        );

        $categoryStruct->id = (int)$conn->lastInsertId();

        return $categoryStruct;
    }

    /**
     * @param ModelStruct $model
     *
     * @return CategoryStruct[]
     * @throws PDOException
     */
    public static function getCategoriesByModel(ModelStruct $model): array
    {
        $sql = "SELECT * FROM qa_categories WHERE id_model = :id_model " .
            " ORDER BY COALESCE(id_parent, 0) ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_CLASS, CategoryStruct::class);
        $stmt->execute(
            [
                'id_model' => $model->id
            ]
        );

        return $stmt->fetchAll();
    }

    /**
     * Returns a JSON encoded representation of categories and subcategories
     *
     * @param int $id_model
     *
     * @return list<array{label: mixed, id: int, severities: list<array{label: mixed, penalty: mixed, sort: mixed, code?: mixed}>, options: list<array{key: string, value: mixed}>, subcategories: list<array{label: mixed, id: mixed, options: list<array{key: string, value: mixed}>, severities: list<array{label: mixed, penalty: mixed, sort: mixed, code?: mixed}>}>}>
     * @throws PDOException
     */
    public static function getCategoriesAndSeverities(int $id_model): array
    {
        $sql = "SELECT * FROM qa_categories WHERE id_model = :id_model ORDER BY COALESCE(id_parent, 0) ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(
            [
                'id_model' => $id_model
            ]
        );

        $out = [];
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result as $row) {
            $severities = self::extractSeverities($row);
            $options = self::extractOptions($row);

            if ($row['id_parent'] == null) {
                // process as parent
                $out[$row['id']] = [];
                $out[$row['id']]['subcategories'] = [];

                $out[$row['id']]['label'] = $row['label'];
                $out[$row['id']]['id'] = (int)$row['id'];
                $out[$row['id']]['options'] = $options;
                $out[$row['id']]['severities'] = $severities;
            } else {
                // process as a child
                $current = [
                    'label' => $row['label'],
                    'id' => $row['id'],
                    'options' => $options,
                    'severities' => $severities
                ];

                $out[$row['id_parent']]['subcategories'][] = $current;
            }
        }

        return array_map(function (array $element): array {
            return [
                'label' => $element['label'] ?? null,
                'id' => (int)($element['id'] ?? 0),
                'severities' => $element['severities'] ?? [],
                'options' => $element['options'] ?? [],
                'subcategories' => $element['subcategories']
            ];
        }, array_values($out));
    }

    /**
     * @param array{severities: string} $json
     *
     * @return list<array{label: mixed, penalty: mixed, sort: mixed, code?: mixed}>
     */
    private static function extractSeverities(array $json): array
    {
        return array_map(function (array $element): array {
            $return = [
                'label' => $element['label'],
                'penalty' => $element['penalty'],
                'sort' => $element['sort'] ?? null
            ];

            if (isset($element['code'])) {
                $return['code'] = $element['code'];
            }

            return $return;
        }, array_values(json_decode($json['severities'], true)));
    }

    /**
     * @param array{options: string} $json
     *
     * @return list<array{key: string, value: mixed}>
     */
    private static function extractOptions(array $json): array
    {
        $map = [];
        $options = json_decode($json['options'], true);

        if (!empty($options)) {
            foreach ($options as $key => $value) {
                $allowedKeys = [
                    'code',
                    'sort'
                ];

                if (in_array($key, $allowedKeys)) {
                    $map[] = [
                        'key' => $key,
                        'value' => $value
                    ];
                }
            }
        }

        return $map;
    }
}
