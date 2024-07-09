<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MimeTypes;

use LogicException;
use MimeTypes\Constants\MimeTypesMap;
use MimeTypes\Guesser\FileBinaryMimeTypeGuesser;
use MimeTypes\Guesser\FileExtensionMimeTypeGuesser;
use MimeTypes\Guesser\FileinfoMimeTypeGuesser;
use MimeTypes\Guesser\MimeTypeGuesserInterface;
use MimeTypes\Guesser\SimpleMarkupMimeTypeGuesser;

/**
 * Manages MIME types and file extensions.
 *
 * For MIME type guessing, you can register custom guessers
 * by calling the registerGuesser() method.
 * Custom guessers are always called before any default ones:
 *
 *     $guesser = new MimeTypes();
 *     $guesser->registerGuesser(new MyCustomMimeTypeGuesser());
 *
 * If you want to change the order of the default guessers, just re-register your
 * preferred one as a custom one. The last registered guesser is preferred over
 * previously registered ones.
 *
 * Re-registering a built-in guesser also allows you to configure it:
 *
 *     $guesser = new MimeTypes();
 *     $guesser->registerGuesser(new FileinfoMimeTypeGuesser('/path/to/magic/file'));
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MimeTypes {
    private array $extensions = [];
    private array $mimeTypes  = [];

    /**
     * @var MimeTypeGuesserInterface[]
     */
    private array $guessers = [];

    /**
     * @var MimeTypes
     */
    private static MimeTypes $default;

    /**
     * MimeTypes constructor.
     *
     * @param array $map
     */
    public function __construct( array $map = [] ) {
        foreach ( $map as $mimeType => $extensions ) {
            $this->extensions[ $mimeType ] = $extensions;

            foreach ( $extensions as $extension ) {
                $this->mimeTypes[ $extension ][] = $mimeType;
            }
        }

        $this->registerGuesser( new FileinfoMimeTypeGuesser() );
        $this->registerGuesser( new FileExtensionMimeTypeGuesser() );
        $this->registerGuesser( new FileBinaryMimeTypeGuesser() );
        $this->registerGuesser( new SimpleMarkupMimeTypeGuesser() );
    }

    /**
     * @param MimeTypes $default
     */
    public static function setDefault( self $default ) {
        self::$default = $default;
    }

    /**
     * @return MimeTypes
     */
    public static function getDefault(): MimeTypes {
        return self::$default ??= new self();
    }

    /**
     * Registers a MIME type guesser.
     *
     * The last registered guesser has precedence over the other ones.
     *
     * @param MimeTypeGuesserInterface $guesser
     */
    public function registerGuesser( MimeTypeGuesserInterface $guesser ): void {
        array_unshift( $this->guessers, $guesser );
    }

    /**
     * @param string $mimeType
     *
     * @return array|mixed|null
     */
    public function getExtensions( string $mimeType ) {
        if ( $this->extensions ) {
            $extensions = $this->extensions[ $mimeType ] ?? $this->extensions[ $lcMimeType = strtolower( $mimeType ) ] ?? null;
        }

        return $extensions ?? MimeTypesMap::MAP[ $mimeType ] ?? MimeTypesMap::MAP[ $lcMimeType ?? strtolower( $mimeType ) ] ?? [];
    }

    /**
     * @param string $ext
     *
     * @return array
     */
    public function getMimeTypes( string $ext ): array {
        if ( $this->mimeTypes ) {
            $mimeTypes = $this->mimeTypes[ $ext ] ?? $this->mimeTypes[ $lcExt = strtolower( $ext ) ] ?? null;
        }

        return $mimeTypes ?? MimeTypesMap::REVERSE_MAP[ $ext ] ?? MimeTypesMap::REVERSE_MAP[ $lcExt ?? strtolower( $ext ) ] ?? [];
    }

    /**
     * @return bool
     */
    public function isGuesserSupported(): bool {
        foreach ( $this->guessers as $guesser ) {
            if ( $guesser->isGuesserSupported() ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The file is passed to each registered MIME type guesser in reverse order
     * of their registration (last registered is queried first). Once a guesser
     * returns a value that is not null, this method terminates and returns the
     * value.
     *
     * @param string $path
     *
     * @return null
     */
    public function guessMimeType( string $path ): ?string {
        // 1. SimpleMarkupMimeTypeGuesser
        // 2. FileBinaryMimeTypeGuesser
        // 3. FileExtensionMimeTypeGuesser
        // 4. FileinfoMimeTypeGuesser
        foreach ( $this->guessers as $guesser ) {
            if ( !$guesser->isGuesserSupported() ) {
                continue;
            }

            $mimeType = $guesser->guessMimeType( $path );

            // if $mimeType is 'application/octet-stream', try with another guesser until the last one
            if ( $mimeType === 'application/octet-stream' and $guesser !== end( $this->guessers ) ) {
                continue;
            }

            if ( null !== $mimeType ) {
                return $mimeType;
            }
        }

        if ( !$this->isGuesserSupported() ) {
            throw new LogicException( 'Unable to guess the MIME type as no guessers are available (have you enabled the php_fileinfo extension?).' );
        }

        return null;
    }
}