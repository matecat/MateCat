<?php

use DataAccess\ShapelessConcreteStruct;

class Revise_FeedbackDAO extends DataAccess_AbstractDao {

    const TABLE = "revision_feedbacks";

    const STRUCT_TYPE = "Revise_FeedbackStruct";

    /**
     * @param Revise_FeedbackStruct $feedbackStruct
     *
     * @return int
     */
    public function insertOrUpdate( Revise_FeedbackStruct $feedbackStruct ) {

        $query = "INSERT INTO  " . self::TABLE . " (id_job, password, revision_number, feedback) 
                VALUES (:id_job, :password, :revision_number, :feedback)
				ON DUPLICATE KEY UPDATE
                feedback = :feedback
                ";

        $values = [
                'id_job'          => $feedbackStruct->id_job,
                'password'        => $feedbackStruct->password,
                'revision_number' => $feedbackStruct->revision_number,
                'feedback'        => $feedbackStruct->feedback,
        ];

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( $values );

        return $stmt->rowCount();
    }

    /**
     * @param $id_job
     * @param $revision_number
     *
     * @return DataAccess_IDaoStruct
     */
    public function getFeedback( $id_job, $revision_number ) {
        $query = "SELECT feedback FROM  " . self::TABLE . " 
                WHERE
                id_job = :id_job AND
                revision_number = :revision_number
            ";

        $values = [
                'id_job'          => $id_job,
                'revision_number' => $revision_number
        ];

        $stmt = $this->database->getConnection()->prepare( $query );
        $object = $this->_fetchObject( $stmt, new ShapelessConcreteStruct(), $values );

        if(isset($object[0])){
            return $object[0];
        }
    }
}
