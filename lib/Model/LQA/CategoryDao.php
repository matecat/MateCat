<?php

namespace LQA;

class CategoryDao extends \DataAccess_AbstractDao {
    protected function _buildResult( $array_result ) {

    }

    public static function findById( $id ) {
        $sql = "SELECT * FROM qa_categories WHERE id = :id LIMIT 1" ;
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute(array('id' => $id));
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\CategoryStruct' );
        return $stmt->fetch();
    }

    public static function createRecord( $data ) {
        $sql = "INSERT INTO qa_categories " .
            " ( id_model, label, id_parent, severities ) " .
            " VALUES " .
            " ( :id_model, :label, :id_parent, :severities )" ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute(
            array(
                'id_model'  => $data['id_model'],
                'label'     => $data['label'] ,
                'id_parent' => $data['id_parent'],
                'severities' => $data['severities']
            )
        );
        $lastId = $conn->lastInsertId();
        return self::findById( $lastId );
    }

    /**
     * @param ModelStruct $model
     *
     * @return \LQA\CategoryStruct[]
     */
    public static function getCategoriesByModel( \LQA\ModelStruct $model ) {
        $sql = "SELECT * FROM qa_categories WHERE id_model = :id_model " .
                " ORDER BY COALESCE(id_parent, 0) ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\CategoryStruct' );
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
     * @return string
     */

    public static function getSerializedModel( $id_model ) {
        $sql = "SELECT * FROM qa_categories WHERE id_model = :id_model " .
            " ORDER BY COALESCE(id_parent, 0) ";

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
            if ( $row['id_parent'] == null ) {
                // process as parent
                $out[ $row['id'] ] = array();
                $out[ $row['id'] ]['subcategories'] = array();

                $out[ $row['id'] ]['label'] = $row['label'];
                $out[ $row['id'] ]['id'] = $row['id'];
                $out[ $row['id'] ]['severities'] = json_decode( $row['severities'], true );
            }

            else {
                // process as child
                $current = array(
                    'label'      => $row['label'],
                    'id'         => $row['id'],
                    'severities' => json_decode( $row['severities'], true)
                );

                $out[ $row['id_parent'] ]['subcategories'][] = $current ;
            }
        }

        $categories = array_map(function($element) {
            return array(
                'label'         => $element['label'],
                'id'            => $element['id'],
                'severities'    => $element['severities'] ,
                'subcategories' => $element['subcategories']
            );
        }, array_values($out) );

        return json_encode( array('categories' => $categories ) );
    }


}
