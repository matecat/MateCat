<?php

/**
 * Made only for code coverage purpose
 * @group regression
 * @covers DataAccess_AbstractDao
 * User: dinies
 * Date: 19/04/16
 * Time: 16.50
 */
class AbstractMethodsTest extends AbstractTest
{
    /**
     * @group regression
     * @covers DataAccess_AbstractDao::create
     */
    public function test_create()
    {
        $DAO= new EnginesModel_EngineDAO(Database::obtain());
      //  $this->setExpectedException("Exception");

    }

}