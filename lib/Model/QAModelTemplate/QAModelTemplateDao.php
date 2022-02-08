<?php

namespace QAModelTemplate;

use DataAccess_AbstractDao;

class QAModelTemplateDao extends DataAccess_AbstractDao {

    const TABLE = "qa_model_templates";

    /**
     * validate a json against schema and then
     * create a QA model template from it
     *
     * @param      $json
     * @param null $uid
     *
     * @return int
     * @throws \Swaggest\JsonSchema\InvalidValue
     * @throws \Exception
     */
    public static function createFromJSON($json, $uid = null)
    {
        $jsonSchema = file_get_contents( __DIR__ . '/../../../inc/qa_model/schema.json' );
        $validator = new \Validator\JSONValidator($jsonSchema);

        if(!empty($validate = $validator->validate($json))){
            throw $validate[0];
        }

        $QAModelTemplateStruct = QAModelTemplateStruct::hydrateFromJSON($json);

        if($uid){
            $QAModelTemplateStruct->uid = $uid;
        }

        return self::save($QAModelTemplateStruct);
    }

    public static function remove($id)
    {}

    public static function getAll()
    {}

    public static function getByUser($uid)
    {}

    /**
     * @param $id
     *
     * @return QAModelTemplateStruct
     */
    public static function get($id)
    {
        $conn = \Database::obtain()->getConnection();

        $stmt = $conn->prepare( "SELECT * FROM qa_model_templates WHERE id=:id " );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, QAModelTemplateStruct::class );
        $stmt->execute([
            'id' => $id
        ]);

        $QAModelTemplateStruct = $stmt->fetch();

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
        $stmt = $conn->prepare( "SELECT * FROM qa_model_template_categories WHERE id_template=:id_template " );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, QAModelTemplateCategoryStruct::class );
        $stmt->execute([
            'id_template' => $QAModelTemplateStruct->id
        ]);

        $QAModelTemplateCategoryStructs = $stmt->fetchAll();

        foreach ($QAModelTemplateCategoryStructs as $QAModelTemplateCategoryStruct){
            $stmt = $conn->prepare( "SELECT * FROM qa_model_template_severities WHERE id_category=:id_category " );
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

    public static function update(QAModelTemplateStruct $modelTemplateStruct)
    {

    }

    /**
     * @param QAModelTemplateStruct $modelTemplateStruct
     *
     * @return string
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

            foreach ($modelTemplateStruct->passfail->thresholds as $thresholdStruct){
                $thresholdStruct->id_passfail = $QAModelTemplatePassfailId;
                $stmt = $conn->prepare( "INSERT INTO qa_model_template_passfail_options (id_passfail, passfail_label, passfail_value) VALUES (:id_passfail, :passfail_label, :passfail_value) " );
                $stmt->execute([
                    'id_passfail' => $thresholdStruct->id_passfail,
                    'passfail_label' => $thresholdStruct->passfail_label,
                    'passfail_value' => $thresholdStruct->passfail_value
                ]);
            }

            foreach ($modelTemplateStruct->categories as $categoryStruct){
                $categoryStruct->id_template = $QAModelTemplateId;
                $stmt = $conn->prepare( "INSERT INTO qa_model_template_categories (id_template, id_parent, category_label, code, dqf_id, sort) 
                    VALUES (:id_template, :id_parent, :category_label, :code, :dqf_id, :sort) " );
                $stmt->execute([
                    'id_template' => $categoryStruct->id_template,
                    'id_parent' => ($categoryStruct->id_parent) ? $categoryStruct->id_parent : null,
                    'category_label' => $categoryStruct->category_label,
                    'code' => $categoryStruct->code,
                    'dqf_id' => ($categoryStruct->dqf_id) ? $categoryStruct->dqf_id : null,
                    'sort' => ($categoryStruct->sort) ? $categoryStruct->sort : null,
                ]);

                $QAModelTemplateCategoryId = $conn->lastInsertId();

                foreach ($categoryStruct->severities as $severityStruct){
                    $severityStruct->id_category = $QAModelTemplateCategoryId;
                    $stmt = $conn->prepare( "INSERT INTO qa_model_template_severities (id_category, severity_label, penalty, sort) 
                    VALUES (:id_category, :severity_label, :penalty, :sort) " );
                    $stmt->execute([
                            'id_category' => $severityStruct->id_category,
                            'severity_label' => $severityStruct->severity_label,
                            'penalty' => $severityStruct->penalty,
                            'sort' => ($severityStruct->sort) ? $severityStruct->sort : null,
                    ]);
                }
            }

            $conn->commit();

            return $QAModelTemplateId;
        } catch (\Exception $exception){
            $conn->rollBack();

            throw $exception;
        }
    }
}