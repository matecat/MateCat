<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 26/06/24
 * Time: 10:46
 *
 */

namespace MimeTypes\Guesser;

use RuntimeException;

/**
 * This class is meant to avoid false positive in mime check for the xml/xliff files and easily extensible to more markup languages.
 *
 * Real case xliff2.0 detected as CSV:
 *
 *  <code>
 *      <source>ქათმის მკერდის ფილე,
 *          უმარილო კარაქი,
 *          კვერცხი,
 *          პურის ნამსხვრევები,
 *          რაფინირებული მზესუმზირის ზეთი,
 *          უმარილო კარაქი
 *      </source>
 *      <target>филе куриной грудки,
 *          несоленое сливочное масло,
 *          яйца,
 *          панировочные сухари,
 *          масло подсолнечное рафинированное, масло
 *          сливочное несоленое
 *      </target>
 *  </code>
 */
class SimpleMarkupMimeTypeGuesser implements MimeTypeGuesserInterface {

    /**
     * @inheritDoc
     */
    public function isGuesserSupported(): bool {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function guessMimeType( string $path ): ?string {

        if ( !( $fp = fopen( $path, "r", false, stream_context_create( null ) ) ) ) {
            throw new RuntimeException( "could not open XML input" );
        }
        $buffer = fread( $fp, 1024 );
        fclose( $fp );

        $r1 = stripos( $buffer, '<?xml' ) !== false;
        $r2 = stripos( $buffer, '<xliff' ) !== false && $r1;
        $r4 = stripos( $buffer, '<tmx' ) !== false && $r1;
        $r3 = stripos( $buffer, '<html' ) !== false;

        if ( $r3 ) {
            return 'text/html';
        }

        if ( $r2 ) {
            return 'application/x-xliff';
        }

        if ( $r1 || $r4 ) {
            return 'text/xml';
        }

        return null;

    }
}