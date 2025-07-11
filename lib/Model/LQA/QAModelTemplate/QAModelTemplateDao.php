<?php

namespace Model\LQA\QAModelTemplate;

use DateTime;
use Exception;
use INIT;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Database;
use Model\Pagination\Pager;
use Model\Pagination\PaginationParameters;
use Model\Projects\ProjectTemplateDao;
use PDO;
use ReflectionException;
use Swaggest\JsonSchema\InvalidValue;
use Utils\Date\DateTimeUtil;
use Utils\Validator\JSONSchema\JSONValidator;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class QAModelTemplateDao extends AbstractDao {

    const query_paginated   = "SELECT id FROM qa_model_templates WHERE deleted_at IS NULL AND uid = :uid LIMIT %u OFFSET %u ";
    const paginated_map_key = __CLASS__ . "::getAllPaginated";

    /**
     * validate a json against schema and then
     * create a QA model template from it
     *
     * @param      $json
     * @param null $uid
     *
     * @return QAModelTemplateStruct
     * @throws InvalidValue
     * @throws Exception
     */
    public static function createFromJSON( $json, $uid = null ): QAModelTemplateStruct {

        $QAModelTemplateStruct = new QAModelTemplateStruct();
        $QAModelTemplateStruct->hydrateFromJSON( $json );

        if ( $uid ) {
            $QAModelTemplateStruct->uid = $uid;
        }

        return self::save( $QAModelTemplateStruct );
    }

    /**
     * @param $json
     *
     * @throws InvalidValue
     * @throws Exception
     */
    private static function validateJSON( $json ) {
        $validatorObject       = new JSONValidatorObject();
        $validatorObject->json = $json;
        $jsonSchema            = file_get_contents( INIT::$ROOT . '/inc/validation/schema/qa_model.json' );
        $validator             = new JSONValidator( $jsonSchema );
        $validator->validate( $validatorObject );

        if ( !$validator->isValid() ) {
            throw $validator->getExceptions()[ 0 ]->error;
        }
    }

    /**
     * @param QAModelTemplateStruct $QAModelTemplateStruct
     * @param                       $json
     *
     * @return QAModelTemplateStruct
     * @throws InvalidValue
     * @throws Exception
     */
    public static function editFromJSON( QAModelTemplateStruct $QAModelTemplateStruct, $json ): QAModelTemplateStruct {

        $QAModelTemplateStruct->hydrateFromJSON( $json );

        return self::update( $QAModelTemplateStruct );
    }

    /**
     * @param int $id
     * @param int $uid
     *
     * @return int
     * @throws ReflectionException
     */
    public static function remove( int $id, int $uid ): int {

        $conn = Database::obtain()->getConnection();
        $conn->beginTransaction();

        try {
            $stmt = $conn->prepare( "UPDATE qa_model_templates SET deleted_at = :now WHERE id = :id AND `deleted_at` IS NULL;" );
            $stmt->execute( [
                    'id'  => $id,
                    'now' => ( new DateTime() )->format( 'Y-m-d H:i:s' )
            ] );

            $deleted = $stmt->rowCount();

            if ( !$deleted ) {
                return 0;
            }

            $stmt = $conn->prepare( "SELECT * FROM qa_model_template_passfails WHERE id_template=:id_template " );
            $stmt->setFetchMode( PDO::FETCH_CLASS, QAModelTemplatePassfailStruct::class );
            $stmt->execute( [
                    'id_template' => $id
            ] );

            $QAModelTemplatePassfailStruct = $stmt->fetch();

            $stmt = $conn->prepare( "DELETE FROM qa_model_template_passfail_options WHERE id_passfail=:id_passfail " );
            $stmt->execute( [
                    'id_passfail' => $QAModelTemplatePassfailStruct->id
            ] );

            $stmt = $conn->prepare( "DELETE FROM qa_model_template_passfails WHERE id_template=:id_template " );
            $stmt->execute( [
                    'id_template' => $id
            ] );

            $stmt = $conn->prepare( "SELECT * FROM qa_model_template_categories WHERE id_template=:id_template " );
            $stmt->setFetchMode( PDO::FETCH_CLASS, QAModelTemplateCategoryStruct::class );
            $stmt->execute( [
                    'id_template' => $id
            ] );

            $QAModelTemplateCategoryStructs = $stmt->fetchAll();

            foreach ( $QAModelTemplateCategoryStructs as $QAModelTemplateCategoryStruct ) {
                $stmt = $conn->prepare( "DELETE FROM qa_model_template_severities WHERE id_category=:id_category " );
                $stmt->execute( [
                        'id_category' => $QAModelTemplateCategoryStruct->id
                ] );
            }

            $stmt = $conn->prepare( "DELETE FROM qa_model_template_categories WHERE id_template=:id_template " );
            $stmt->execute( [
                    'id_template' => $id
            ] );

            ProjectTemplateDao::removeSubTemplateByIdAndUser( $id, $uid, 'qa_model_template_id' );

            $conn->commit();

            return $deleted;

        } catch ( Exception $exception ) {
            $conn->rollBack();

            throw $exception;
        } finally {
            static::destroyQueryPaginated( $uid );
        }
    }

    /**
     * @param $uid
     *
     * @return array
     * @throws Exception
     */
    public static function getDefaultTemplate( $uid ): array {
        $defaultTemplate      = file_get_contents( INIT::$ROOT . '/inc/qa_model.json' );
        $defaultTemplateModel = json_decode( $defaultTemplate, true );

        $categories      = [];
        $idSeverityIndex = 0;

        foreach ( $defaultTemplateModel[ 'model' ][ 'categories' ] as $cindex => $category ) {

            $severities = [];
            unset( $category[ 'dqf_id' ] );
            $category[ 'id' ]   = ( $cindex + 1 );
            $category[ 'sort' ] = ( $cindex + 1 );

            foreach ( $defaultTemplateModel[ 'model' ][ 'severities' ] as $sindex => $severity ) {

                $idSeverityIndex++;

                unset( $severity[ 'dqf_id' ] );
                $severity[ 'id' ]          = $idSeverityIndex;
                $severity[ 'id_category' ] = ( $cindex + 1 );
                $severity[ 'code' ]        = strtoupper( substr( $severity[ 'label' ], 0, 3 ) );
                $severity[ 'penalty' ]     = floatval( $severity[ 'penalty' ] );
                $severity[ 'sort' ]        = ( $sindex + 1 );
                $severities[]              = $severity;
            }

            $category[ 'severities' ] = $severities;

            $categories[] = $category;
        }

        $passFail         = $defaultTemplateModel[ 'model' ][ 'passfail' ];
        $passFail[ 'id' ] = 0;

        $passFail[ 'thresholds' ] = [
                [
                        "id"          => 0,
                        "id_passfail" => 0,
                        "label"       => "R1",
                        "value"       => (int)$passFail[ 'options' ][ 'limit' ][ 0 ],
                ],
                [
                        "id"          => 0,
                        "id_passfail" => 0,
                        "label"       => "R2",
                        "value"       => (int)$passFail[ 'options' ][ 'limit' ][ 1 ],
                ]
        ];

        unset( $passFail[ 'options' ] );

        $now = ( new DateTime() )->format( 'Y-m-d H:i:s' );

        return [
                'id'         => 0,
                'uid'        => (int)$uid,
                'label'      => 'Matecat original settings',
                'version'    => 1,
                'categories' => $categories,
                'passfail'   => $passFail,
                'createdAt'  => DateTimeUtil::formatIsoDate( $now ),
                'modifiedAt' => DateTimeUtil::formatIsoDate( $now ),
                'deletedAt'  => null,
        ];

    }

    /**
     * @param int $uid
     * @param int $current
     * @param int $pagination
     * @param int $ttl
     *
     * @return array
     * @throws ReflectionException
     */
    public static function getAllPaginated( int $uid, string $baseRoute, int $current = 1, int $pagination = 20, int $ttl = 60 * 60 * 24 ): array {

        $conn = Database::obtain()->getConnection();

        $pager  = new Pager( $conn );
        $totals = $pager->count(
                "SELECT count(id) FROM qa_model_templates WHERE deleted_at IS NULL AND uid = :uid",
                [ 'uid' => $uid ]
        );

        $paginationParameters = new PaginationParameters( self::query_paginated, [ 'uid' => $uid ], ShapelessConcreteStruct::class, $baseRoute, $current, $pagination );
        $paginationParameters->setCache( self::paginated_map_key . ":" . $uid, $ttl );

        $result = $pager->getPagination( $totals, $paginationParameters );

        $models = [];
        foreach ( $result[ 'items' ] as $model ) {
            $models[] = self::get( [
                    'id'  => $model[ 'id' ],
                    'uid' => $uid
            ] );
        }

        $result[ 'items' ] = $models;

        return $result;
    }

    /**
     * @throws Exception
     */
    public static function getQaModelTemplateByIdAndUid( PDO $conn, array $meta = [] ) {

        $query  = "SELECT * FROM qa_model_templates WHERE deleted_at IS NULL ";
        $params = [];

        if ( empty( $meta[ 'id' ] ) || empty( $meta[ 'uid' ] ) ) {
            throw new Exception( "id and uid parameters must be provided." );
        }

        $query          .= " AND id=:id ";
        $params[ 'id' ] = $meta[ 'id' ];

        $query           .= " AND uid=:uid ";
        $params[ 'uid' ] = $meta[ 'uid' ];

        $stmt = $conn->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_CLASS, QAModelTemplateStruct::class );
        $stmt->execute( $params );

        return $stmt->fetch();

    }

    /**
     * @param array $meta
     *
     * @return QAModelTemplateStruct
     * @throws Exception
     */
    public static function get( array $meta = [] ): ?QAModelTemplateStruct {

        $conn = Database::obtain()->getConnection();

        $QAModelTemplateStruct = self::getQaModelTemplateByIdAndUid( $conn, $meta );

        if ( !$QAModelTemplateStruct ) {
            return null;
        }

        // qa_model_template_passfails
        $stmt = $conn->prepare( "SELECT * FROM qa_model_template_passfails WHERE id_template=:id_template " );
        $stmt->setFetchMode( PDO::FETCH_CLASS, QAModelTemplatePassfailStruct::class );
        $stmt->execute( [
                'id_template' => $QAModelTemplateStruct->id
        ] );

        $QAModelTemplatePassfailStruct = $stmt->fetch();

        $stmt = $conn->prepare( "SELECT * FROM qa_model_template_passfail_options WHERE id_passfail=:id_passfail " );
        $stmt->setFetchMode( PDO::FETCH_CLASS, QAModelTemplatePassfailThresholdStruct::class );
        $stmt->execute( [
                'id_passfail' => $QAModelTemplatePassfailStruct->id
        ] );

        $QAModelTemplatePassfailStruct->thresholds = $stmt->fetchAll();

        // qa_model_template_categories
        $stmt = $conn->prepare( "SELECT * FROM qa_model_template_categories WHERE id_template=:id_template ORDER BY sort ASC" );
        $stmt->setFetchMode( PDO::FETCH_CLASS, QAModelTemplateCategoryStruct::class );
        $stmt->execute( [
                'id_template' => $QAModelTemplateStruct->id
        ] );

        $QAModelTemplateCategoryStructs = $stmt->fetchAll();

        foreach ( $QAModelTemplateCategoryStructs as $QAModelTemplateCategoryStruct ) {
            $stmt = $conn->prepare( "SELECT * FROM qa_model_template_severities WHERE id_category=:id_category ORDER BY sort ASC " );
            $stmt->setFetchMode( PDO::FETCH_CLASS, QAModelTemplateSeverityStruct::class );
            $stmt->execute( [
                    'id_category' => $QAModelTemplateCategoryStruct->id
            ] );

            $QAModelTemplateCategoryStruct->severities = $stmt->fetchAll();
        }

        $QAModelTemplateStruct->categories = $QAModelTemplateCategoryStructs;
        $QAModelTemplateStruct->passfail   = $QAModelTemplatePassfailStruct;

        return $QAModelTemplateStruct;
    }

    /**
     * @param QAModelTemplateStruct $modelTemplateStruct
     *
     * @return QAModelTemplateStruct
     * @throws Exception
     */
    public static function save( QAModelTemplateStruct $modelTemplateStruct ): QAModelTemplateStruct {
        $conn = Database::obtain()->getConnection();
        $conn->beginTransaction();

        try {
            $stmt = $conn->prepare( "INSERT INTO qa_model_templates (uid, version, label) VALUES (:uid, :version, :label) " );
            $stmt->execute( [
                    'version' => $modelTemplateStruct->version,
                    'label'   => $modelTemplateStruct->label,
                    'uid'     => $modelTemplateStruct->uid,
            ] );

            $QAModelTemplateId = $conn->lastInsertId();

            $modelTemplateStruct->passfail->id_template = $QAModelTemplateId;
            $stmt                                       = $conn->prepare( "INSERT INTO qa_model_template_passfails ( id_template, passfail_type) VALUES ( :id_template, :passfail_type) " );
            $stmt->execute( [
                    'passfail_type' => $modelTemplateStruct->passfail->passfail_type,
                    'id_template'   => $modelTemplateStruct->passfail->id_template
            ] );

            $QAModelTemplatePassfailId         = $conn->lastInsertId();
            $modelTemplateStruct->passfail->id = $QAModelTemplatePassfailId;

            foreach ( $modelTemplateStruct->passfail->thresholds as $thresholdStruct ) {
                $thresholdStruct->id_passfail = $QAModelTemplatePassfailId;
                $stmt                         = $conn->prepare( "INSERT INTO qa_model_template_passfail_options (id_passfail, passfail_label, passfail_value) VALUES (:id_passfail, :passfail_label, :passfail_value) " );
                $stmt->execute( [
                        'id_passfail'    => $thresholdStruct->id_passfail,
                        'passfail_label' => $thresholdStruct->passfail_label,
                        'passfail_value' => $thresholdStruct->passfail_value
                ] );

                $thresholdStruct->id = $conn->lastInsertId();
            }

            foreach ( $modelTemplateStruct->categories as $csort => $categoryStruct ) {
                $categoryStruct->id_template = $QAModelTemplateId;
                $stmt                        = $conn->prepare( "INSERT INTO qa_model_template_categories (id_template, id_parent, category_label, code, sort) 
                    VALUES (:id_template, :id_parent, :category_label, :code, :sort) " );
                $stmt->execute( [
                        'id_template'    => $categoryStruct->id_template,
                        'id_parent'      => ( $categoryStruct->id_parent ) ? $categoryStruct->id_parent : null,
                        'category_label' => $categoryStruct->category_label,
                        'code'           => $categoryStruct->code,
                        'sort'           => (int)( $categoryStruct->sort ) ? $categoryStruct->sort : (int)( $csort + 1 ),
                ] );

                $QAModelTemplateCategoryId = $conn->lastInsertId();
                $categoryStruct->id        = $QAModelTemplateCategoryId;

                foreach ( $categoryStruct->severities as $ssort => $severityStruct ) {
                    $severityStruct->id_category = $QAModelTemplateCategoryId;
                    $stmt                        = $conn->prepare( "INSERT INTO qa_model_template_severities (id_category, severity_label, severity_code, penalty, sort) 
                    VALUES (:id_category, :severity_label, :severity_code, :penalty, :sort) " );
                    $stmt->execute( [
                            'id_category'    => $severityStruct->id_category,
                            'severity_label' => $severityStruct->severity_label,
                            'penalty'        => $severityStruct->penalty,
                            'severity_code'  => $severityStruct->severity_code,
                            'sort'           => (int)( $severityStruct->sort ) ? $severityStruct->sort : (int)( $ssort + 1 ),
                    ] );

                    $severityStruct->id = $conn->lastInsertId();
                }
            }

            $conn->commit();

            $modelTemplateStruct->id = $QAModelTemplateId;

            return $modelTemplateStruct;
        } catch ( Exception $exception ) {
            $conn->rollBack();

            throw $exception;
        } finally {
            static::destroyQueryPaginated( $modelTemplateStruct->uid );
        }
    }

    /**
     * @param QAModelTemplateStruct $modelTemplateStruct
     *
     * @return mixed
     * @throws Exception
     */
    public static function update( QAModelTemplateStruct $modelTemplateStruct ) {
        $conn = Database::obtain()->getConnection();
        $conn->beginTransaction();

        try {
            $stmt = $conn->prepare( "UPDATE qa_model_templates SET uid=:uid, version=:version, label=:label, modified_at=:modified_at WHERE id=:id" );
            $stmt->execute( [
                    'version'     => $modelTemplateStruct->version,
                    'label'       => $modelTemplateStruct->label,
                    'uid'         => $modelTemplateStruct->uid,
                    'id'          => $modelTemplateStruct->id,
                    'modified_at' => ( new DateTime() )->format( 'Y-m-d H:i:s' )
            ] );

            // UPSERT
            $stmt = $conn->prepare( "DELETE from qa_model_template_passfails WHERE id_template=:id_template " );
            $stmt->execute( [
                    'id_template' => $modelTemplateStruct->id,
            ] );

            $stmt = $conn->prepare( "DELETE from qa_model_template_categories WHERE id_template=:id_template " );
            $stmt->execute( [
                    'id_template' => $modelTemplateStruct->id,
            ] );

            $stmt = $conn->prepare( "INSERT INTO qa_model_template_passfails (id_template, passfail_type) VALUES (:id_template,:passfail_type )" );
            $stmt->execute( [
                    'passfail_type' => $modelTemplateStruct->passfail->passfail_type,
                    'id_template'   => $modelTemplateStruct->id,
            ] );

            $idPassfail                        = $conn->lastInsertId();
            $modelTemplateStruct->passfail->id = $idPassfail;

            foreach ( $modelTemplateStruct->passfail->thresholds as $thresholdStruct ) {
                $stmt = $conn->prepare( "INSERT INTO qa_model_template_passfail_options (id_passfail,passfail_label,passfail_value) 
                    VALUES (:id_passfail,:passfail_label,:passfail_value) " );
                $stmt->execute( [
                        'id_passfail'    => $idPassfail,
                        'passfail_label' => $thresholdStruct->passfail_label,
                        'passfail_value' => $thresholdStruct->passfail_value,
                ] );

                $thresholdStruct->id          = $conn->lastInsertId();
                $thresholdStruct->id_passfail = $idPassfail;
            }

            foreach ( $modelTemplateStruct->categories as $csort => $categoryStruct ) {
                $stmt = $conn->prepare( "INSERT INTO qa_model_template_categories (id_template,id_parent,category_label, code, sort) 
                    VALUES (:id_template,:id_parent,:category_label,:code,:sort) " );
                $stmt->execute( [
                        'id_template'    => $categoryStruct->id_template,
                        'id_parent'      => ( $categoryStruct->id_parent ) ? $categoryStruct->id_parent : null,
                        'category_label' => $categoryStruct->category_label,
                        'code'           => $categoryStruct->code,
                        'sort'           => ( $categoryStruct->sort ) ? (int)$categoryStruct->sort : (int)( $csort + 1 ),
                ] );

                $idCategory         = $conn->lastInsertId();
                $categoryStruct->id = $idCategory;

                foreach ( $categoryStruct->severities as $ssort => $severityStruct ) {
                    $stmt = $conn->prepare( "INSERT INTO qa_model_template_severities (id_category,severity_label,severity_code, penalty, sort)
                        VALUES (:id_category, :severity_label, :severity_code, :penalty, :sort) " );
                    $stmt->execute( [
                            'id_category'    => $idCategory,
                            'severity_label' => $severityStruct->severity_label,
                            'severity_code'  => $severityStruct->severity_code,
                            'penalty'        => $severityStruct->penalty,
                            'sort'           => ( $severityStruct->sort ) ? (int)$severityStruct->sort : (int)( $ssort + 1 ),
                    ] );

                    $severityStruct->id          = $conn->lastInsertId();
                    $severityStruct->id_category = $idCategory;
                }
            }

            $conn->commit();

            return $modelTemplateStruct;
        } catch ( Exception $exception ) {
            $conn->rollBack();

            throw $exception;
        } finally {
            static::destroyQueryPaginated( $modelTemplateStruct->uid );
        }
    }

    /**
     * @param int $uid
     *
     * @throws ReflectionException
     */
    private
    static function destroyQueryPaginated( int $uid ) {
        ( new static() )->_deleteCacheByKey( self::paginated_map_key . ":" . $uid, false );
    }

}