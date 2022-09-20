<?php

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;

/**
 * Class DefuseEncryption.
 * This class has the responsibility to encrypt and decrypt a text based on a Key
 * @see \Defuse\Crypto\Core
 */
class DefuseEncryption {

    private $key;
    private $keyFilePath;

    public function __construct( $keyFilePath ) {
        $this->keyFilePath = $keyFilePath;

        if( $this->loadEncryptionKey() === false ) {
            $this->createEncryptionKey();
        }
    }

    /**
     * Loads the encryption key from the file.
     * @return bool True if successfully load
     */
    public function loadEncryptionKey() {
        if( file_exists( $this->keyFilePath ) ) {
            $keyFile = fopen( $this->keyFilePath, 'r' );

            if( $keyFile ) {
                $keyFileContents = fread( $keyFile, filesize( $this->keyFilePath ) );
                fclose( $keyFile );

                if( $keyFileContents ) {
                    $this->key = Key::loadFromAsciiSafeString( $keyFileContents );
                    return true;
                }
            }
        }

        $this->key = null;

        return false;
    }

    /**
     * Creates a new encryption key and saves in the file.
     * @throws Exception
     */
    public function createEncryptionKey() {
        $this->generateKey();
        $keyFile = fopen( $this->keyFilePath, 'w' );

        if( $keyFile === false ) {
            throw new Exception( 'Failed to open the file.' );
        }

        if( fwrite( $keyFile, $this->key ) === FALSE ) {
            throw new Exception( 'Failed to write in the file.' );
        }

        fclose( $keyFile );

        $this->loadEncryptionKey();
    }

    /**
     * Generates a new key.
     */
    public function generateKey() {
        $this->key = Key::createNewRandomKey()->saveToAsciiSafeString();
    }

    /**
     * Encrypts a text and returns the encrypted text
     * @param $text
     * @return string   Encrypted text
     */
    public function encrypt( $text ) {
        $cipherText = Crypto::encrypt( $text, $this->key );

        return $cipherText;
    }

    /**
     * Decrypts an encrypted text
     * @param $cipherText
     * @return bool|string  Decrypted text or FALSE when found an error in decryption
     */
    public function decrypt( $cipherText ) {
        try {
            return Crypto::decrypt( $cipherText, $this->key );
        } catch ( WrongKeyOrModifiedCiphertextException $ex ) {
            return false;
        }
    }

}