<?php
/**
 * User: domenico
 * Date: 09/10/13
 * Time: 15.21
 *
 */

abstract class AbstractTest extends PHPUnit_Framework_TestCase {

    protected $thisTest;

    protected $reflectedClass;
    protected $reflectedMethod;

    public function setUp() {
        parent::setUp();
        $this->thisTest = microtime( true );
    }

    public function tearDown() {
        parent::tearDown();
        $resultTime = microtime( true ) - $this->thisTest;
        echo " " . str_pad( get_class( $this ) . " " . $this->getName( false ), 35, " ", STR_PAD_RIGHT ) . " - Did in " . $resultTime . " seconds.\n";
    }

    /**
     * @return mixed
     */
    protected function getTheLastInsertIdByQuery( Database $database_instance ) {
        $stmt = $database_instance->getConnection()->query( "SELECT LAST_INSERT_ID()" );
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    /**
     * Return the raw query from a prepared query:
     *
     * Example
     * ----------------------------------------
     * Convert this:
     *
     * array(2) {
     *    [0] => string(36) "SELECT * FROM engines WHERE id = :id"
     *    [1] =>
     *    array(1) {
     *       'id' => int(10)
     *    }
     * }
     *
     * into this:
     *
     * SELECT * FROM engines WHERE id = 10
     *
     * @param array $preparedQuery
     *
     * @return string
     *
     */
    protected function getRawQuery(array $preparedQuery) {
        $rawQuery  = $preparedQuery[0];
        foreach ( $preparedQuery[1] as $key => $value){
            if(is_string($value)){
                $value = '\''.$value.'\'';
            }

            $rawQuery = str_replace(':'.$key, $value, $rawQuery);
        }

        return $rawQuery;
    }
}

