<?php

namespace Utils\Tools;

/**
 * Builds the Content-Disposition header value of a download (RFC 6266).
 *
 * The file name is the user's own, in any script. `filename="…"` carries an ASCII copy every client reads;
 * `filename*=UTF-8''…` (RFC 5987) carries the original, and clients that understand it prefer it.
 * A name that is already plain ASCII gets the `filename` parameter alone, exactly as before.
 */
final class ContentDisposition
{
    /**
     * @param string $filename
     * @param string $type `attachment` or `inline`
     *
     * @return string
     */
    public static function build(string $filename, string $type = 'attachment'): string
    {
        $safeFilename = self::sanitize($filename);
        $asciiFilename = self::toAscii($safeFilename);

        $header = $type . '; filename="' . $asciiFilename . '"';

        if ($asciiFilename !== $safeFilename) {
            $header .= "; filename*=UTF-8''" . rawurlencode($safeFilename);
        }

        return $header;
    }

    /**
     * Removes the characters that could break out of the quoted value (double quote, backslash, CR, LF, NUL):
     * a quote would let the rest of the name be read as further parameters, a CR or LF would end the header.
     *
     * @param string $filename
     *
     * @return string
     */
    public static function sanitize(string $filename): string
    {
        return str_replace(['"', '\\', "\r", "\n", "\0"], '', $filename);
    }

    /**
     * One underscore per character outside printable ASCII, and for `%` and `;`, which some clients decode or split on.
     *
     * @param string $filename
     *
     * @return string
     */
    private static function toAscii(string $filename): string
    {
        // a name that is not valid UTF-8 is replaced byte by byte
        $ascii = preg_replace('/[^\x20-\x7E]/u', '_', $filename) ?? preg_replace('/[^\x20-\x7E]/', '_', $filename);

        return str_replace(['%', ';'], '_', (string)$ascii);
    }
}
