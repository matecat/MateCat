<?php

use Phinx\Migration\AbstractMigration;

class ApiKeysTable extends AbstractMigration {
    public function up() {

        $table = $this->table('api_keys');

        $table->addColumn('uid', 'biginteger', array('null' => false))
            ->addColumn('api_key', 'string', array(
                'limit' => '45', 'null' => false
            ))
            ->addColumn('api_secret', 'string', array(
                'limit' => '45', 'null' => false
            ))
            ->addColumn('create_date', 'datetime', array('null' => false))
            ->addColumn('last_update', 'datetime', array('null' => false))
            ->addColumn('enabled', 'boolean', array(
                'null' => false, 'default' => true
            ))
            ->save();
    }

    public function down() {

        $this->dropTable('api_keys');

    }
}
