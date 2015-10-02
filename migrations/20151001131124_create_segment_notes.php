<?php

use Phinx\Migration\AbstractMigration;

class CreateSegmentNotes extends AbstractMigration {
    public function up() {
      $table = $this->table('segment_notes');  
      $table
        ->addColumn('id_segment', 'biginteger', array('null' => false))
        ->addColumn('note', 'text', array('null' => false))
        ->addIndex(array('id_segment'))
        ->save();
    }

    public function down() {
      $this->dropTable('segment_notes');
    }
}
