<?php

namespace migrations;

use Phinx\Migration\AbstractMigration;
use Utils\Logger\LoggerFactory;

/**
 * Class AbstractMatecatMigration
 *
 */
class AbstractMatecatMigration extends AbstractMigration {

    public $sql_up;
    public $sql_down;

    public function up() {
        if ( !is_array( $this->sql_up ) ) {
            $this->sql_up = [ $this->sql_up ];
        }

        foreach ( $this->sql_up as $sql ) {
            LoggerFactory::doJsonLog( $sql . "\n" );
            $this->execute( $sql );
        }
    }

    public function down() {

        if ( !is_array( $this->sql_down ) ) {
            $this->sql_down = [ $this->sql_down ];
        }

        foreach ( $this->sql_down as $sql ) {
            $this->execute( $sql );
        }
    }
}
