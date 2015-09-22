<?php

use Phinx\Migration\AbstractMigration;

class CreateOwnerFeatures extends AbstractMigration
{
    public function up() {
        $table = $this->table('owner_features');

        $table
            ->addColumn('uid', 'biginteger', array('null' => false))
            ->addColumn('feature_code', 'string', array(
                'limit' => '45', 'null' => false
            ))
            ->addColumn('options', 'text')
            ->addColumn('create_date', 'datetime', array('null' => false))
            ->addColumn('last_update', 'datetime', array('null' => false))
            ->addColumn('enabled', 'boolean', array(
                'null' => false, 'default' => true
            ))

            ->addIndex(array('uid', 'feature_code' ), array('unique' => true))
            ->save();
    }

    public function down() {
        $this->dropTable('owner_features');
    }
}
