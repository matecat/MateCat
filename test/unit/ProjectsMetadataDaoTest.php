<?php

class ProjectsMetadataDaoTest extends AbstractTest {

    function testCreateNewKey() {
        $dao = new Projects_MetadataDao(Database::obtain());
        $record = $dao->get(1, 'foo');
        $this->assertEquals($record, false);

        $record = $dao->set(1, 'foo', 'bar');

        $this->assertEquals( $record->value, 'bar');
        $this->assertEquals( $record->key, 'foo');
    }

    function testUpdate() {
        $dao = new Projects_MetadataDao(Database::obtain());
        $record = $dao->set(1, 'foo', 'bar');
        $record = $dao->set(1, 'foo', 'bar2');

        $this->assertEquals( $record->value, 'bar2');
        $this->assertEquals( $record->key, 'foo');

        $count = $dao->allByProjectId(1);
        $this->assertEquals( 1, count($count) );
    }

    function testDelete() {
        $dao = new Projects_MetadataDao(Database::obtain());
        $record = $dao->set(1, 'foo', 'bar2');
        $record = $dao->delete(1, 'foo');

        $count = $dao->allByProjectId(1);
        $this->assertEquals( 0, count($count) );
    }

}
