<?php

namespace Model\ReviseFeedback;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\ShapelessConcreteStruct;
use ReflectionException;

class FeedbackDAO extends AbstractDao {

    const TABLE = "revision_feedbacks";

    const STRUCT_TYPE = "\\Revise\\FeedbackStruct";

    /**
     * @param FeedbackStruct $feedbackStruct
     *
     * @return int
     */
    public function insertOrUpdate( FeedbackStruct $feedbackStruct ): int {

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
     * @param $old_password
     * @param $new_password
     * @param $revision_number
     *
     * @return int
     */
    public function updateFeedbackPassword( $id_job, $old_password, $new_password, $revision_number ): int {
        $query = "UPDATE " . self::TABLE . " 
                SET password = :new_password
                WHERE
                id_job = :id_job AND 
                password = :old_password AND
                revision_number = :revision_number
            ";

        $values = [
                'id_job'          => $id_job,
                'old_password'    => $old_password,
                'new_password'    => $new_password,
                'revision_number' => $revision_number
        ];

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( $values );

        return $stmt->rowCount();
    }

    /**
     * @param $id_job
     * @param $password
     * @param $revision_number
     *
     * @return ShapelessConcreteStruct
     * @throws ReflectionException
     */
    public function getFeedback( $id_job, $password, $revision_number ): ?ShapelessConcreteStruct {
        $query = "SELECT feedback FROM  " . self::TABLE . " 
                WHERE
                id_job = :id_job AND 
                password = :password AND
                revision_number = :revision_number
            ";

        $values = [
                'id_job'          => $id_job,
                'password'        => $password,
                'revision_number' => $revision_number
        ];

        $stmt   = $this->database->getConnection()->prepare( $query );
        $object = $this->_fetchObjectMap( $stmt, ShapelessConcreteStruct::class, $values );

        return $object[ 0 ] ?? null;

    }
}
