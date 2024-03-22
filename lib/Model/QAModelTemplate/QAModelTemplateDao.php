<?php

namespace QAModelTemplate;

use DataAccess_AbstractDao;
use Date\DateTimeUtil;
use DateTime;

class QAModelTemplateDao extends DataAccess_AbstractDao
{

    /**
     * validate a json against schema and then
     * create a QA model template from it
     *
     * @param      $json
     * @param null $uid
     *
     * @return QAModelTemplateStruct
     * @throws \Swaggest\JsonSchema\InvalidValue
     * @throws \Exception
     */
    public static function createFromJSON($json, $uid = null)
    {
        self::validateJSON($json);

        $QAModelTemplateStruct = new QAModelTemplateStruct();
        $QAModelTemplateStruct->hydrateFromJSON($json);

        if($uid){
            $QAModelTemplateStruct->uid = $uid;
        }

        return self::save($QAModelTemplateStruct);
    }

    /**
     * @param $json
     * @throws \Swaggest\JsonSchema\InvalidValue
     * @throws \Exception
     */
    private static function validateJSON($json)
    {
        $validatorObject = new \Validator\JSONValidatorObject();
        $validatorObject->json = $json;
        $jsonSchema = file_get_contents( __DIR__ . '/../../../inc/validation/schema/qa_model.json' );
        $validator = new \Validator\JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        if(!$validator->isValid()){
            throw $validator->getErrors()[0]->error;
        }
    }

    /**
     * @param QAModelTemplateStruct $QAModelTemplateStruct
     * @param                       $json
     *
     * @return QAModelTemplateStruct
     * @throws \Swaggest\JsonSchema\InvalidValue
     * @throws \Exception
     */
    public static function editFromJSON(QAModelTemplateStruct $QAModelTemplateStruct, $json)
    {
        self::validateJSON($json);
        $QAModelTemplateStruct->hydrateFromJSON($json);

        return self::update($QAModelTemplateStruct);
    }

    /**
     * @param $id
     *
     * @throws \Exception
     */
    public static function remove($id)
    {
        $qaTemplateModel = self::get([
            'id' => $id
        ]);
        $uid = $qaTemplateModel->uid;

        $conn = \Database::obtain()->getConnection();
        $conn->beginTransaction();

        try {
            $stmt = $conn->prepare( "DELETE FROM qa_model_templates WHERE id = :id " );
            $stmt->execute([
                'id' => $id
            ]);

            $stmt = $conn->prepare( "SELECT * FROM qa_model_template_passfails WHERE id_template=:id_template " );
            $stmt->setFetchMode( \PDO::FETCH_CLASS, QAModelTemplatePassfailStruct::class );
            $stmt->execute([
                    'id_template' => $id
            ]);

            $QAModelTemplatePassfailStruct = $stmt->fetch();

            $stmt = $conn->prepare( "DELETE FROM qa_model_template_passfail_options WHERE id_passfail=:id_passfail " );
            $stmt->execute([
                    'id_passfail' => $QAModelTemplatePassfailStruct->id
            ]);

            $stmt = $conn->prepare( "DELETE FROM qa_model_template_passfails WHERE id_template=:id_template " );
            $stmt->execute([
                'id_template' => $id
            ]);

            $stmt = $conn->prepare( "SELECT * FROM qa_model_template_categories WHERE id_template=:id_template " );
            $stmt->setFetchMode( \PDO::FETCH_CLASS, QAModelTemplateCategoryStruct::class );
            $stmt->execute([
                    'id_template' => $id
            ]);

            $QAModelTemplateCategoryStructs = $stmt->fetchAll();

            foreach ($QAModelTemplateCategoryStructs as $QAModelTemplateCategoryStruct) {
                $stmt = $conn->prepare( "DELETE FROM qa_model_template_severities WHERE id_category=:id_category " );
                $stmt->execute([
                        'id_category' => $QAModelTemplateCategoryStruct->id
                ]);
            }

            $stmt = $conn->prepare( "DELETE FROM qa_model_template_categories WHERE id_template=:id_template " );
            $stmt->execute([
                    'id_template' => $id
            ]);

            $stmt = $conn->prepare( "UPDATE project_templates SET qa_model_template_id = :zero WHERE uid = :uid and qa_model_template_id = :id " );
            $stmt->execute( [
                'zero' => 0,
                'id'   => $id,
                'uid'  => $uid,
            ] );

            $conn->commit();
        } catch (\Exception $exception){
            $conn->rollBack();

            throw $exception;
        }
    }

    /**
     * @param $uid
     * @return array
     * @throws \Exception
     */
    private static function getDefaultTemplate($uid)
    {
        $defaultTemplate = file_get_contents( __DIR__ . '/../../../inc/qa_model.json' );
        $defaultTemplateModel = json_decode($defaultTemplate, true);

        $categories = [];
        $idSeverityIndex = 0;

        foreach ($defaultTemplateModel['model']['categories'] as $cindex => $category){

            $severities = [];
            unset($category['dqf_id']);
            $category['id'] = ($cindex+1);

            foreach ($defaultTemplateModel['model']['severities'] as $sindex => $severity){

                $idSeverityIndex++;

                unset($severity['dqf_id']);
                $severity['id'] = $idSeverityIndex;
                $severity['id_category'] = ($cindex+1);
                $severity['code'] = strtoupper(substr($severity['label'], 0, 3));
                $severity['penalty'] = floatval($severity['penalty']);
                $severities[] = $severity;
            }

            $category['severities'] = $severities;

            $categories[] = $category;
        }

        $passfail = $defaultTemplateModel['model']['passfail'];
        $passfail['id'] = 0;

        $passfail['thresholds'] = [
            [
                "id" => 0,
                "id_passfail" => 0,
                "label" => "R1",
                "value" => (int)$passfail['options']['limit'][0],
            ],
            [
                "id" => 0,
                "id_passfail" => 0,
                "label" => "R2",
                "value" => (int)$passfail['options']['limit'][1],
            ]
        ];

        unset($passfail['options']);

        $now = (new DateTime())->format('Y-m-d H:i:s');

        return [
            'id' => 0,
            'uid' => (int)$uid,
            'label' => 'Default',
            'version' => 1,
            'categories' => $categories,
            'passfail' => $passfail,
            'createdAt' => DateTimeUtil::formatIsoDate($now),
            'modifiedAt' => DateTimeUtil::formatIsoDate($now),
            'deletedAt' => null,
        ];

    }

    /**
     * @param int $uid
     * @param int $current
     * @param int $pagination
     *
     * @return array
     */
    public static function getAllPaginated($uid, $current = 1, $pagination = 20)
    {
        $conn = \Database::obtain()->getConnection();

        $stmt = $conn->prepare( "SELECT count(id) as count FROM qa_model_templates WHERE deleted_at IS NULL AND uid = :uid");
        $stmt->execute([
            'uid' => $uid
        ]);

        $count = $stmt->fetch(\PDO::FETCH_ASSOC);
        $pages = ceil($count['count'] / $pagination);
        $prev = ($current !== 1) ? "/api/v3/qa_model_template?page=".($current-1) : null;
        $next = ($current < $pages) ? "/api/v3/qa_model_template?page=".($current+1) : null;
        $offset = ($current - 1) * $pagination;

        $models = [];
        $models[] = self::getDefaultTemplate($uid);

        $stmt = $conn->prepare( "SELECT id FROM qa_model_templates WHERE deleted_at IS NULL AND uid = :uid LIMIT $pagination OFFSET $offset ");
        $stmt->execute([
            'uid' => $uid
        ]);

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $model){
            $models[] = self::get([
                'id' => $model['id'],
                'uid' => $uid
            ]);
        }

        return [
            'current_page' => (int)$current,
            'per_page' => (int)$pagination,
            'last_page' => (int)$pages,
            'prev' => $prev,
            'next' => $next,
            'items' => $models,
        ];
    }

    /**
     * @param $uid
     *
     * @return QAModelTemplateStruct
     */
    public static function getByUser($uid)
    {
        return self::get([
            'uid' => $uid
        ]);
    }

    /**
     * @param array $meta
     *
     * @return QAModelTemplateStruct
     */
    public static function get(array $meta = [])
    {
        $conn = \Database::obtain()->getConnection();
        $query = "SELECT * FROM qa_model_templates WHERE deleted_at IS NULL ";
        $params = [];

        if(isset($meta['id']) and '' !== $meta['id'] ){
            $query .= " AND id=:id ";
            $params['id'] = $meta['id'];
        }

        if(isset($meta['uid']) and '' !== $meta['uid'] ){
            $query .= " AND uid=:uid ";
            $params['uid'] = $meta['uid'];
        }

        $stmt = $conn->prepare( $query );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, QAModelTemplateStruct::class );
        $stmt->execute($params);

        $QAModelTemplateStruct = $stmt->fetch();

        if(!$QAModelTemplateStruct){
            return null;
        }

        // qa_model_template_passfails
        $stmt = $conn->prepare( "SELECT * FROM qa_model_template_passfails WHERE id_template=:id_template " );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, QAModelTemplatePassfailStruct::class );
        $stmt->execute([
                'id_template' => $QAModelTemplateStruct->id
        ]);

        $QAModelTemplatePassfailStruct = $stmt->fetch();

        $stmt = $conn->prepare( "SELECT * FROM qa_model_template_passfail_options WHERE id_passfail=:id_passfail " );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, QAModelTemplatePassfailThresholdStruct::class );
        $stmt->execute([
                'id_passfail' => $QAModelTemplatePassfailStruct->id
        ]);

        $QAModelTemplatePassfailStruct->thresholds = $stmt->fetchAll();

        // qa_model_template_categories
        $stmt = $conn->prepare( "SELECT * FROM qa_model_template_categories WHERE id_template=:id_template ORDER BY sort ASC" );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, QAModelTemplateCategoryStruct::class );
        $stmt->execute([
            'id_template' => $QAModelTemplateStruct->id
        ]);

        $QAModelTemplateCategoryStructs = $stmt->fetchAll();

        foreach ($QAModelTemplateCategoryStructs as $QAModelTemplateCategoryStruct){
            $stmt = $conn->prepare( "SELECT * FROM qa_model_template_severities WHERE id_category=:id_category ORDER BY sort ASC " );
            $stmt->setFetchMode( \PDO::FETCH_CLASS, QAModelTemplateSeverityStruct::class );
            $stmt->execute([
                'id_category' => $QAModelTemplateCategoryStruct->id
            ]);

            $QAModelTemplateCategoryStruct->severities = $stmt->fetchAll();
        }

        $QAModelTemplateStruct->categories = $QAModelTemplateCategoryStructs;
        $QAModelTemplateStruct->passfail = $QAModelTemplatePassfailStruct;

        return $QAModelTemplateStruct;
    }

    /**
     * @param QAModelTemplateStruct $modelTemplateStruct
     *
     * @return QAModelTemplateStruct
     * @throws \Exception
     */
    public static function save(QAModelTemplateStruct $modelTemplateStruct)
    {
        $conn = \Database::obtain()->getConnection();
        $conn->beginTransaction();

        try {
            $stmt = $conn->prepare( "INSERT INTO qa_model_templates (uid, version, label) VALUES (:uid, :version, :label) " );
            $stmt->execute([
                    'version' => $modelTemplateStruct->version,
                    'label' => $modelTemplateStruct->label,
                    'uid' => $modelTemplateStruct->uid,
            ]);

            $QAModelTemplateId = $conn->lastInsertId();

            $modelTemplateStruct->passfail->id_template = $QAModelTemplateId;
            $stmt = $conn->prepare( "INSERT INTO qa_model_template_passfails ( id_template, passfail_type) VALUES ( :id_template, :passfail_type) " );
            $stmt->execute([
                    'passfail_type' => $modelTemplateStruct->passfail->passfail_type,
                    'id_template' => $modelTemplateStruct->passfail->id_template
            ]);

            $QAModelTemplatePassfailId = $conn->lastInsertId();
            $modelTemplateStruct->passfail->id = $QAModelTemplatePassfailId;

            foreach ($modelTemplateStruct->passfail->thresholds as $thresholdStruct){
                $thresholdStruct->id_passfail = $QAModelTemplatePassfailId;
                $stmt = $conn->prepare( "INSERT INTO qa_model_template_passfail_options (id_passfail, passfail_label, passfail_value) VALUES (:id_passfail, :passfail_label, :passfail_value) " );
                $stmt->execute([
                    'id_passfail' => $thresholdStruct->id_passfail,
                    'passfail_label' => $thresholdStruct->passfail_label,
                    'passfail_value' => $thresholdStruct->passfail_value
                ]);

                $thresholdStruct->id = $conn->lastInsertId();
            }

            foreach ($modelTemplateStruct->categories as $csort => $categoryStruct){
                $categoryStruct->id_template = $QAModelTemplateId;
                $stmt = $conn->prepare( "INSERT INTO qa_model_template_categories (id_template, id_parent, category_label, code, sort) 
                    VALUES (:id_template, :id_parent, :category_label, :code, :sort) " );
                $stmt->execute([
                    'id_template' => $categoryStruct->id_template,
                    'id_parent' => ($categoryStruct->id_parent) ? $categoryStruct->id_parent : null,
                    'category_label' => $categoryStruct->category_label,
                    'code' => $categoryStruct->code,
                    'sort' => (int)($categoryStruct->sort) ? $categoryStruct->sort : (int)($csort+1),
                ]);

                $QAModelTemplateCategoryId = $conn->lastInsertId();
                $categoryStruct->id = $QAModelTemplateCategoryId;

                foreach ($categoryStruct->severities as $ssort => $severityStruct){
                    $severityStruct->id_category = $QAModelTemplateCategoryId;
                    $stmt = $conn->prepare( "INSERT INTO qa_model_template_severities (id_category, severity_label, severity_code, penalty, sort) 
                    VALUES (:id_category, :severity_label, :severity_code, :penalty, :sort) " );
                    $stmt->execute([
                            'id_category' => $severityStruct->id_category,
                            'severity_label' => $severityStruct->severity_label,
                            'penalty' => $severityStruct->penalty,
                            'severity_code' => $severityStruct->severity_code,
                            'sort' => (int)($severityStruct->sort) ? $severityStruct->sort : (int)($ssort+1),
                    ]);

                    $severityStruct->id = $conn->lastInsertId();
                }
            }

            $conn->commit();

            $modelTemplateStruct->id = $QAModelTemplateId;

            return $modelTemplateStruct;
        } catch (\Exception $exception){
            $conn->rollBack();

            throw $exception;
        }
    }

    /**
     * @param QAModelTemplateStruct $modelTemplateStruct
     *
     * @return mixed
     * @throws \Exception
     */
    public static function update(QAModelTemplateStruct $modelTemplateStruct)
    {
        $conn = \Database::obtain()->getConnection();
        $conn->beginTransaction();

        try {
            $stmt = $conn->prepare( "UPDATE qa_model_templates SET uid=:uid, version=:version, label=:label WHERE id=:id" );
            $stmt->execute([
                'version' => $modelTemplateStruct->version,
                'label' => $modelTemplateStruct->label,
                'uid' => $modelTemplateStruct->uid,
                'id' => $modelTemplateStruct->id,
            ]);

            // UPSERT
            $stmt = $conn->prepare( "DELETE from qa_model_template_passfails WHERE id_template=:id_template " );
            $stmt->execute([
                'id_template' => $modelTemplateStruct->id,
            ]);

            $stmt = $conn->prepare( "DELETE from qa_model_template_categories WHERE id_template=:id_template " );
            $stmt->execute([
                'id_template' => $modelTemplateStruct->id,
            ]);

            $stmt = $conn->prepare( "INSERT INTO qa_model_template_passfails (id_template, passfail_type) VALUES (:id_template,:passfail_type )" );
            $stmt->execute([
                'passfail_type' => $modelTemplateStruct->passfail->passfail_type,
                'id_template' => $modelTemplateStruct->id,
            ]);

            $idPassfail = $conn->lastInsertId();
            $modelTemplateStruct->passfail->id = $idPassfail;

            foreach ($modelTemplateStruct->passfail->thresholds as $thresholdStruct){
                $stmt = $conn->prepare( "INSERT INTO qa_model_template_passfail_options (id_passfail,passfail_label,passfail_value) 
                    VALUES (:id_passfail,:passfail_label,:passfail_value) " );
                $stmt->execute([
                    'id_passfail' => $idPassfail,
                    'passfail_label' => $thresholdStruct->passfail_label,
                    'passfail_value' => $thresholdStruct->passfail_value,
                ]);

                $thresholdStruct->id = $conn->lastInsertId();
                $thresholdStruct->id_passfail = $idPassfail;
            }

            foreach ($modelTemplateStruct->categories as $csort => $categoryStruct){
                $stmt = $conn->prepare( "INSERT INTO qa_model_template_categories (id_template,id_parent,category_label, code, sort) 
                    VALUES (:id_template,:id_parent,:category_label,:code,:sort) " );
                $stmt->execute([
                        'id_template' => $categoryStruct->id_template,
                        'id_parent' => ($categoryStruct->id_parent) ? $categoryStruct->id_parent : null,
                        'category_label' => $categoryStruct->category_label,
                        'code' => $categoryStruct->code,
                        'sort' => ($categoryStruct->sort) ? (int)$categoryStruct->sort : (int)($csort+1),
                ]);

                $idCategory = $conn->lastInsertId();
                $categoryStruct->id = $idCategory;

                foreach ($categoryStruct->severities as $ssort => $severityStruct){
                    $stmt = $conn->prepare( "INSERT INTO qa_model_template_severities (id_category,severity_label,severity_code, penalty, sort)
                        VALUES (:id_category, :severity_label, :severity_code, :penalty, :sort) " );
                    $stmt->execute([
                        'id_category' => $idCategory,
                        'severity_label' => $severityStruct->severity_label,
                        'severity_code' => $severityStruct->severity_code,
                        'penalty' => $severityStruct->penalty,
                        'sort' => ($severityStruct->sort) ? (int)$severityStruct->sort : (int)($ssort+1),
                    ]);

                    $severityStruct->id = $conn->lastInsertId();
                    $severityStruct->id_category = $idCategory;
                }
            }

            $conn->commit();

            return $modelTemplateStruct;
        } catch (\Exception $exception){
            $conn->rollBack();

            throw $exception;
        }
    }
}