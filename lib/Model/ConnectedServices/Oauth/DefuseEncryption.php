<?php

namespace Model\ConnectedServices\Oauth;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Defuse\Crypto\Key;
use Exception;

/**
 * Class DefuseEncryption.
 * This class has the responsibility to encrypt and decrypt a text based on a Key
 * @see \Defuse\Crypto\Core
 */
class DefuseEncryption {

    private ?Key   $key = null;
    private string $keyFilePath;

    /**
     * @throws Exception
     */
    public function __construct( string $keyFilePath ) {
        $this->keyFilePath = $keyFilePath;

        if ( $this->loadEncryptionKey() === false ) {
            $this->createEncryptionKey();
        }
    }

    /**
     * Loads the encryption key from the file.
     * @return bool True if successfully load
     * @throws EnvironmentIsBrokenException
     * @throws BadFormatException
     */
    public function loadEncryptionKey(): bool {
        if ( file_exists( $this->keyFilePath ) ) {
            $keyFile = fopen( $this->keyFilePath, 'r' );

            if ( $keyFile ) {
                $keyFileContents = fread( $keyFile, filesize( $this->keyFilePath ) );
                fclose( $keyFile );

                if ( $keyFileContents ) {
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

        try {

            $keyFile = fopen( $this->keyFilePath, 'w' );

            if ( $keyFile === false ) {
                throw new Exception( 'Failed to open the file.' );
            }

            if ( fwrite( $keyFile, $this->generateKey() ) === false ) {
                throw new Exception( 'Failed to write in the file.' );
            }

        } finally {
            if ( $keyFile ) {
                fclose( $keyFile );
                $this->loadEncryptionKey();
            }
        }

    }

    /**
     * Generates a new key.
     * @throws EnvironmentIsBrokenException
     */
    public function generateKey(): string {
        return Key::createNewRandomKey()->saveToAsciiSafeString();
    }

    /**
     * Encrypts a text and returns the encrypted text
     *
     * @param $text
     *
     * @return string   Encrypted text
     * @throws EnvironmentIsBrokenException
     */
    public function encrypt( $text ): string {
        return Crypto::encrypt( $text, $this->key );
    }

    /**
     * Decrypts an encrypted text
     *
     * @param $cipherText
     *
     * @return null|string  Decrypted text or FALSE when found an error in decryption
     * @throws EnvironmentIsBrokenException
     */
    public function decrypt( $cipherText ): ?string {
        try {
            return Crypto::decrypt( $cipherText, $this->key );
        } catch ( WrongKeyOrModifiedCiphertextException $ex ) {
            return null;
        }
    }

}