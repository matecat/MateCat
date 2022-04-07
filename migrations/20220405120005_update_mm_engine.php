<?php

class UpdateMmEngine extends AbstractMatecatMigration {

    public $sql_up = [ '
        UPDATE `engines` SET `name` = \'MyMemory (<a href="https://guides.matecat.com/my" target="_blank">Details</a>)\', description=\'Machine translation by the MT engine best suited to your project\' WHERE id = 1 ;
    ' ];

    public $sql_down = [ 'UPDATE `matecat.engines`  SET `name` = "MyMemory (All)" WHERE id = 1;' ];
}
