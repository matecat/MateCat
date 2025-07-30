<?php

namespace Conversion\MimeTypes\Guesser;

use Conversion\MimeTypes\Constants\MimeTypesMap;

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
            $mimeList = MimeTypesMap::REVERSE_MAP[ $pathinfo[ 'extension' ] ];
            return array_pop( $mimeList );
        }

        return null;
    }
}