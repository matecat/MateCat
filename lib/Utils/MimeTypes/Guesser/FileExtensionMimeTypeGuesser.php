<?php

namespace MimeTypes\Guesser;

use MimeTypes\Constants\MimeTypesMap;

class FileExtensionMimeTypeGuesser implements MimeTypeGuesserInterface {
    /**
     * @inheritDoc
     */
    public function isGuesserSupported(): bool {
        return function_exists( 'pathinfo' );
    }

    /**
     * @inheritDoc
     */
    public function guessMimeType( string $path ): ?string {
        $pathinfo = pathinfo( $path );

        if ( empty( $pathinfo ) ) {
            return null;
        }

        if ( !isset( $pathinfo[ 'extension' ] ) ) {
            return null;
        }

        if ( isset( MimeTypesMap::REVERSE_MAP[ $pathinfo[ 'extension' ] ] ) ) {
            return array_pop( MimeTypesMap::REVERSE_MAP[ $pathinfo[ 'extension' ] ] );
        }

        return null;
    }
}