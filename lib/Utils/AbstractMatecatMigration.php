<?php

/**
 * Class AbstractMatecatMigration
 *
 */
class AbstractMatecatMigration extends \Phinx\Migration\AbstractMigration {

    public $sql_up;
    public $sql_down;

    public function up() {
        if ( !is_array( $this->sql_up ) ) {
            $this->sql_up = array( $this->sql_up ) ;
        }

        foreach( $this->sql_up as $sql ) {
            Log::doJsonLog( $sql . "\n" );
            $this->execute($sql);
        }
    }

    public function down() {

        if ( !is_array( $this->sql_down ) ) {
            $this->sql_down = array( $this->sql_down ) ;
        }

        foreach( $this->sql_down as $sql ) {
            $this->execute($sql);
        }
    }
}
