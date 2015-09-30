<?php

use Phinx\Migration\AbstractMigration;

class AddIndexToApiKey extends AbstractMigration
{
    public function change()
    {

       $api_keys = $this->table('api_keys');  
       $api_keys->addIndex(array('api_key'), array('unique' => true))
            ->save();

    }
}
