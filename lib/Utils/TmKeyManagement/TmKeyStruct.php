<?php

namespace Utils\TmKeyManagement;
use DomainException;
use JsonSerializable;
use stdClass;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 02/09/14
 * Time: 13.35
 */
class TmKeyStruct extends stdClass implements JsonSerializable {

    /**
     * @var bool This key is for tm. 0 or 1
     */
    public ?bool $tm = true;

    /**
     * @var bool This key is for glossary. 0 or 1
     */
    public ?bool $glos = true;

    /**
     * A flag that indicates whether the key has been created by the owner or not
     * @var ?bool 0 or 1
     */
    public ?bool $owner = null;

    /**
     * @var int|null The uid of the translator that uses that key in the job.
     */
    public ?int $uid_transl = null;

    /**
     * @var int|null The uid of the revisor that uses that key in the job.
     */
    public ?int $uid_rev = null;

    /**
     * @var ?string The key's name
     */
    public ?string $name = null;

    /**
     * @var ?string
     */
    public ?string $key = null;

    /**
     * @var ?bool Read grant for owner. 0 or 1
     */
    public ?bool $r = null;

    /**
     * @var ?bool Write grant for owner. 0 or 1
     */
    public ?bool $w = null;

    /**
     * @var ?bool Read grant for translator. 0 or 1
     */
    public ?bool $r_transl = null;

    /**
     * @var ?bool Write grant for translator. 0 or 1
     */
    public ?bool $w_transl = null;

    /**
     * @var ?bool Read grant for revisor. 0 or 1
     */
    public ?bool $r_rev = null;

    /**
     * @var ?bool Write grant for revisor. 0 or 1
     */
    public ?bool $w_rev = null;

    /**
     * @var ?string Source language string. It must be compliant to RFC3066.<br />
     *             <b>Example</b><br />en-US, fr-FR, en-GB
     * @link http://www.i18nguy.com/unicode/language-identifiers.html
     * @link https://tools.ietf.org/html/rfc3066
     *
     */
    public ?string $source = null;


    /**
     * @var ?string Target language string. It must be compliant to RFC3066.<br />
     *             <b>Example</b><br />en-US, fr-FR, en-GB
     * @link http://www.i18nguy.com/unicode/language-identifiers.html
     * @link https://tools.ietf.org/html/rfc3066
     *
     */
    public ?string $target = null;

    /**
     * Coupled with $in_users
     *  if the key is shared:
     *      $in_users > 1 and $is_shared == true
     * @var bool
     */
    public bool $is_shared = false;

    /**
     * @var int How much readable chars for hashed keys
     */
    protected int $readable_chars = 5;

    /**
     * Used exclusively for JSON rendering purposes
     *
     * @var bool
     */
    public bool $complete_format = false;

    /**
     * @var int
     */
    public int $penalty = 0;

    /**
     * When a key return back from the client we have to know if it is hashed
     *
     * @return bool
     */
    public function isEncryptedKey(): bool {

        $keyLength = strlen( $this->key );

        return substr( $this->key, 0, $keyLength - $this->readable_chars ) == str_repeat( "*", $keyLength - $this->readable_chars );

    }

    public function isShared(): bool {
        return $this->is_shared;
    }

    /**
     * @param array|TmKeyStruct|null $params                 An associative array with the following keys:<br/>
     *                                                       <pre>
     *                                                       tm         : boolean - Tm key
     *                                                       glos       : boolean - Glossary key
     *                                                       owner      : boolean - The key is set by the Project creator
     *                                                       uid_transl : int     - User ID
     *                                                       uid_rev    : int     - User ID
     *                                                       name       : string
     *                                                       key        : string
     *                                                       r          : boolean - Read privilege
     *                                                       w          : boolean - Write privilege
     *                                                       r_transl   : boolean - Translator Read privilege
     *                                                       w_transl   : boolean - Translator Write privilege
     *                                                       r_rev      : boolean - Revisor Read privilege
     *                                                       w_rev      : boolean - Translator Write privilege
     *                                                       source     : string  - Source languages
     *                                                       target     : string  - Target languages
     *                                                       </pre>
     */
    public function __construct( $params = null ) {
        if ( $params != null ) {
            foreach ( $params as $property => $value ) {
                if ( property_exists( $this, $property ) ) {
                    $this->$property = $value;
                }
            }
        }
    }

    public function __set( $name, $value ) {
        if ( !property_exists( $this, $name ) ) {
            throw new DomainException( 'Unknown property ' . $name );
        }
    }

    /**
     * Converts the current object into an associative array
     * @return array
     */
    public function toArray(): array {
        return json_decode( json_encode( $this ), true );
    }

    /**
     * @param TmKeyStruct $obj
     *
     * @return bool
     */
    public function equals( TmKeyStruct $obj ): bool {
        return $this->key == $obj->key;
    }


    public function getCrypt(): string {

        $keyLength   = strlen( $this->key );
        $last_digits = substr( $this->key, -$this->readable_chars );

        return str_repeat( "*", $keyLength - $this->readable_chars ) . $last_digits;

    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {

        if ( $this->complete_format ) {
            return [
                    'tm'         => $this->tm,
                    'glos'       => $this->glos,
                    'owner'      => $this->owner,
                    'uid_transl' => $this->uid_transl,
                    'uid_rev'    => $this->uid_rev,
                    'name'       => $this->name,
                    'key'        => $this->key,
                    'r'          => (int)$this->r,
                    'w'          => (int)$this->w,
                    'r_transl'   => $this->r_transl,
                    'w_transl'   => $this->w_transl,
                    'r_rev'      => $this->r_rev,
                    'w_rev'      => $this->w_rev,
                    'penalty'    => $this->penalty ?? 0,
                    'is_shared'  => $this->is_shared,
                    'is_private' => $this->isEncryptedKey()
            ];
        }

        return [
                'tm'        => $this->tm,
                'glos'      => $this->glos,
                'owner'     => $this->owner,
                'name'      => $this->name,
                'key'       => $this->key,
                'penalty'   => $this->penalty ?? 0,
                'is_shared' => $this->is_shared,
        ];
    }
}