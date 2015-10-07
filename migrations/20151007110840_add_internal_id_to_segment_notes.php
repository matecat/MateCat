<?php

use Phinx\Migration\AbstractMigration;

class AddInternalIdToSegmentNotes extends AbstractMigration
{
    public function change() {
      $table = $this->table('segment_notes');  
      $table
        ->addColumn('internal_id', 'string', array('limit' => 100, 'null' => false))
        ->save();
    }
}
