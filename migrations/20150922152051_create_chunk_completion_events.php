<?php

use Phinx\Migration\AbstractMigration;

class CreateChunkCompletionEvents extends AbstractMigration
{
    public function up() {
      $table = $this->table('chunk_completion_events');
      $table
        ->addColumn('id_job', 'biginteger', array('null' => false))
        ->addColumn('uid', 'biginteger', array('null' => true))

        ->addColumn('job_first_segment', 'biginteger',
          array('signed' => false, 'null' => false))

        ->addColumn('job_last_segment', 'biginteger',
          array('signed' => false, 'null' => false))

        ->addColumn('password', 'string', array('null' => false, 'limit' => 45 ))
        ->addColumn('source', 'string', array('null' => false, 'limit' => 45))
        ->addColumn('create_date', 'datetime', array('null' => false ))
        ->addColumn('remote_ip_address', 'string', array('limit' => 45, 'null' => false ))

        ->addIndex(array('id_job', 'password' ))
        ->addIndex(array('create_date' ))
        ->save();
    }

    public function down() {
      $this->dropTable('chunk_completion_events');
    }
}
