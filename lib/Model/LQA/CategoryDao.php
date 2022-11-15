<?php

namespace LQA;

use PDO;
use ReflectionException;

class CategoryDao extends \DataAccess_AbstractDao {
    const TABLE = 'qa_categories' ;

    /**
     * @param $id
     *
     * @return mixed
     */
    public static function findById( $id ) {
        $sql = "SELECT * FROM qa_categories WHERE id = :id LIMIT 1" ;
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute(array('id' => $id));
        $stmt->setFetchMode( PDO::FETCH_CLASS, CategoryStruct::class );
        return $stmt->fetch();
    }

    /**
     * @param $id_model
     * @param $id_parent
     *
     * @return mixed
     */
    public function findByIdModelAndIdParent( $id_model, $id_parent ) {
        $sql = "SELECT * FROM qa_categories WHERE id_model = :id_model AND id_parent = :id_parent " ;
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ 'id_model' => $id_model, 'id_parent' => $id_parent ] );
        $stmt->setFetchMode( PDO::FETCH_CLASS, CategoryStruct::class );
        return $stmt->fetchAll();
    }

    /**
     * @param $data
     *
     * @return mixed
     * @throws ReflectionException
     */
    public static function createRecord( $data ) {

        $categoryStruct = new CategoryStruct( $data );

        $sql = "INSERT INTO qa_categories " .
            " ( id_model, label, id_parent, severities, options ) " .
            " VALUES " .
            " ( :id_model, :label, :id_parent, :severities, :options )" ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( $categoryStruct->toArray(
                [
                        'id_model',
                        'label',
                        'id_parent',
                        'options',
                        'severities',
                ]
        ) );

        $categoryStruct->id = $conn->lastInsertId();
        return $categoryStruct;
    }

    /**
     * @param ModelStruct $model
     *
     * @return CategoryStruct[]
     */
    public static function getCategoriesByModel( ModelStruct $model ) {
        $sql = "SELECT * FROM qa_categories WHERE id_model = :id_model " .
                " ORDER BY COALESCE(id_parent, 0) ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, CategoryStruct::class );
        $stmt->execute(
                array(
                        'id_model' => $model->id
                )
        );
        return $stmt->fetchAll();
    }

    /**
     * Returns a json encoded representation of categories and subcategories
     *
     * @param $id_model
     *
     * @return array
     */
    public static function getCategoriesAndSeverities( $id_model ) {
        $sql = "SELECT * FROM qa_categories WHERE id_model = :id_model ORDER BY COALESCE(id_parent, 0) ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute(
            array(
                'id_model'  => $id_model
            )
        );

        $out = array();
        $result = $stmt->fetchAll() ;

        foreach($result as $row) {

            $severities = self::extractSeverities($row);
            $options = self::extractOptions($row);

            if ( $row['id_parent'] == null ) {
                // process as parent
                $out[ $row['id'] ] = array();
                $out[ $row['id'] ]['subcategories'] = array();

                $out[ $row['id'] ]['label'] = $row['label'];
                $out[ $row['id'] ]['id'] = (int)$row['id'];
                $out[ $row['id'] ]['options'] = $options;
                $out[ $row['id'] ]['severities'] = $severities;

            } else {
                // process as child
                $current = array(
                    'label'      => $row['label'],
                    'id'         => $row['id'],
                    'options'    => $options,
                    'severities' => $severities
                );

                $out[ $row['id_parent'] ]['subcategories'][] = $current ;
            }
        }

        return array_map(function( $element) {
            return [
                'label'         => $element['label'],
                'id'            => $element['id'],
                'severities'    => $element['severities'] ,
                'options'       => $element['options'] ,
                'subcategories' => $element['subcategories']
            ];
        }, array_values($out) );
    }

    /**
     * @param $json
     *
     * @return array
     */
    private static function extractSeverities($json)
    {
        return array_map(function($element) {
            $return = [
                    'label'   => $element['label'],
                    'penalty' => $element['penalty']
            ];

            if(isset($element['code'])){
                $return['code'] = $element['code'];
            }

            return $return;
        }, array_values(json_decode( $json['severities'], true )));
    }

    /**
     * @param $json
     *
     * @return array
     */
    private static function extractOptions($json)
    {
        return array_map(function($element) {
            return [
                'code' => $element['code']
            ];
        }, array_values(json_decode( $json['options'], true )));
    }
}
