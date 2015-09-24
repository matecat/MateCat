<?php

class API_V2_JobRevisionData extends API_V2_ProtectedKleinController {

    protected function afterConstruct() {

    }

    protected function validateRequest() {
        // TODO: move this query somewhere else
        $pdo = PDOConnection::connectINIT() ; // TODO this is pre PDO in Database::obtain(), change it.
        $stmt = $pdo->connection->prepare(
            'SELECT id FROM jobs WHERE id = :id_job AND password = :password'
        );

        Log::doLog( $this->auth_param );

        $stmt->bindValue( ':id_job', $this->request->id_job );
        $stmt->bindValue( ':password', $this->auth_param );
        $stmt->execute();
        $result = $stmt->fetch();

        if ($result[0]['id'] != (int) $this->request->id_job) {
            $this->response->code(403);
            $this->response->json(array('error' => 'Authentication failed'));
        }

    }

    public function segments() {
        $dao = new JobRevisionDataDao( PDOConnection::connectINIT() );

        $data = $dao->getSegments(
            $this->request->id_job,
            $this->auth_param,
            array('page' => $this->request->param('page', '1'))
        ) ;

        $result = new Json_RevisionData_Segments( $data );
        $this->response->json( $result->render() );

    }

    public function revisionData() {
        $dao = new JobRevisionDataDao( PDOConnection::connectINIT() );

        $data = $dao->getData(
            $this->request->id_job,
            $this->auth_param
        );

        $result = new Json_RevisionData_Job( $data );
        $this->response->json( $result->render() );
    }
}
