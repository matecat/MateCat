<?php

class SpellCheckFactory {

    protected static $_INSTANCE;

    /**
     * @param array $config
     *
     * @return HunspellShell|HunspellSocket
     */
    public static function getInstance( $config = array() ) {

        if( self::$_INSTANCE == null ){

            if ( INIT::$SPELL_CHECK_TRANSPORT_TYPE == 'socket' ) {
                include_once realpath( dirname( __FILE__ ) . '/Hunspell/HunspellSocket.php' );
                self::$_INSTANCE = new HunspellSocket( $config );
            } else {
                include_once realpath( dirname( __FILE__ ) . '/Hunspell/HunspellShell.php' );
                self::$_INSTANCE = new HunspellShell( $config );
            }

            if ( INIT::$SPELL_CHECK_ENABLED ) {
                self::$_INSTANCE->enableSpellCheck();
            }

        }

        return self::$_INSTANCE;

    }

}