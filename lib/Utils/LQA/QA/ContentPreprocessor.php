<?php

namespace Utils\LQA\QA;

use Utils\Tools\CatUtils;

/**
 * Handles content preprocessing for QA analysis.
 *
 * This class is responsible for:
 * - Replacing non-printable ASCII control characters (0x00-0x1F, 0x7F) with placeholders
 * - Converting hex entity references (&#xNN;) to placeholders for DOM compatibility
 * - Filling empty HTML tags with placeholders to prevent DOM parsing issues
 * - Reversing all transformations when producing output
 *
 * The preprocessing is necessary because:
 * 1. DOMDocument cannot handle certain control characters
 * 2. Some entity references are converted back to literals by DOM
 * 3. Empty tags like `<g></g>` can cause DOM normalization issues
 *
 * Placeholder format: `##$_XX$##` where XX is the hex code of the character.
 *
 * @package Utils\LQA\QA
 */
class ContentPreprocessor
{
    /** @var string Placeholder used to fill empty HTML tags */
    public const string EMPTY_HTML_TAGS_PLACEHOLDER = '##$$##______EMPTY_HTML_TAG______##$$##';

    /**
     * Matches a single, well-formed XLIFF tag (opening, closing or self-closing).
     *
     * Only the tag names actually used by MateCat at Layer 1 are recognized
     * (the same set protected by the subfiltering {@see \Matecat\SubFiltering\Filters\PlaceHoldXliffTags}).
     * Any other angle-bracketed sequence is treated as literal text.
     *
     * @var string
     */
    private const string XLIFF_TAG_REGEX = '#</?(?:g|bx|ex|x|bpt|ept|sub|it|mrk|sc|ec|pc|ph)(?:\s[^>]*?)?/?>#si';

    /** @var string Prefix of the temporary placeholder used to shield valid XLIFF tags while escaping stray brackets */
    private const string XLIFF_TAG_PLACEHOLDER_PREFIX = '##$$##__QA_XLIFF_TAG_';

    /** @var string Suffix of the temporary placeholder used to shield valid XLIFF tags while escaping stray brackets */
    private const string XLIFF_TAG_PLACEHOLDER_SUFFIX = '__##$$##';

    private const array ASCII_PLACE_HOLD_MAP = [
        '00' => ['symbol' => 'NULL', 'placeHold' => '##$_00$##', 'numeral' => 0x00],
        '01' => ['symbol' => 'SOH', 'placeHold' => '##$_01$##', 'numeral' => 0x01],
        '02' => ['symbol' => 'STX', 'placeHold' => '##$_02$##', 'numeral' => 0x02],
        '03' => ['symbol' => 'ETX', 'placeHold' => '##$_03$##', 'numeral' => 0x03],
        '04' => ['symbol' => 'EOT', 'placeHold' => '##$_04$##', 'numeral' => 0x04],
        '05' => ['symbol' => 'ENQ', 'placeHold' => '##$_05$##', 'numeral' => 0x05],
        '06' => ['symbol' => 'ACK', 'placeHold' => '##$_06$##', 'numeral' => 0x06],
        '07' => ['symbol' => 'BEL', 'placeHold' => '##$_07$##', 'numeral' => 0x07],
        '08' => ['symbol' => 'BS', 'placeHold' => '##$_08$##', 'numeral' => 0x08],
        '09' => ['symbol' => 'HT', 'placeHold' => '##$_09$##', 'numeral' => 0x09],
        '0A' => ['symbol' => 'LF', 'placeHold' => '##$_0A$##', 'numeral' => 0x0A],
        '0B' => ['symbol' => 'VT', 'placeHold' => '##$_0B$##', 'numeral' => 0x0B],
        '0C' => ['symbol' => 'FF', 'placeHold' => '##$_0C$##', 'numeral' => 0x0C],
        '0D' => ['symbol' => 'CR', 'placeHold' => '##$_0D$##', 'numeral' => 0x0D],
        '0E' => ['symbol' => 'SO', 'placeHold' => '##$_0E$##', 'numeral' => 0x0E],
        '0F' => ['symbol' => 'SI', 'placeHold' => '##$_0F$##', 'numeral' => 0x0F],
        '10' => ['symbol' => 'DLE', 'placeHold' => '##$_10$##', 'numeral' => 0x10],
        '11' => ['symbol' => 'DC', 'placeHold' => '##$_11$##', 'numeral' => 0x11],
        '12' => ['symbol' => 'DC', 'placeHold' => '##$_12$##', 'numeral' => 0x12],
        '13' => ['symbol' => 'DC', 'placeHold' => '##$_13$##', 'numeral' => 0x13],
        '14' => ['symbol' => 'DC', 'placeHold' => '##$_14$##', 'numeral' => 0x14],
        '15' => ['symbol' => 'NAK', 'placeHold' => '##$_15$##', 'numeral' => 0x15],
        '16' => ['symbol' => 'SYN', 'placeHold' => '##$_16$##', 'numeral' => 0x16],
        '17' => ['symbol' => 'ETB', 'placeHold' => '##$_17$##', 'numeral' => 0x17],
        '18' => ['symbol' => 'CAN', 'placeHold' => '##$_18$##', 'numeral' => 0x18],
        '19' => ['symbol' => 'EM', 'placeHold' => '##$_19$##', 'numeral' => 0x19],
        '1A' => ['symbol' => 'SUB', 'placeHold' => '##$_1A$##', 'numeral' => 0x1A],
        '1B' => ['symbol' => 'ESC', 'placeHold' => '##$_1B$##', 'numeral' => 0x1B],
        '1C' => ['symbol' => 'FS', 'placeHold' => '##$_1C$##', 'numeral' => 0x1C],
        '1D' => ['symbol' => 'GS', 'placeHold' => '##$_1D$##', 'numeral' => 0x1D],
        '1E' => ['symbol' => 'RS', 'placeHold' => '##$_1E$##', 'numeral' => 0x1E],
        '1F' => ['symbol' => 'US', 'placeHold' => '##$_1F$##', 'numeral' => 0x1F],
        '7F' => ['symbol' => 'DEL', 'placeHold' => '##$_7F$##', 'numeral' => 0x7F],
    ];

    protected static string $regexpAscii = '/([\x{00}-\x{1F}\x{7F}]{1})/u';
    protected static string $regexpEntity = '/&#x([0-1]{0,1}[0-9A-F]{1,2})/u';
    protected static string $regexpPlaceHoldAscii = '/##\$_([0-1]{0,1}[0-9A-F]{1,2})\$##/u';

    public static function getTabPlaceholder(): string
    {
        return self::ASCII_PLACE_HOLD_MAP['09']['placeHold'];
    }

    public static function getNewlinePlaceholder(): string
    {
        return self::ASCII_PLACE_HOLD_MAP['0A']['placeHold'];
    }

    /**
     * Preprocess a segment by encoding, replacing ASCII chars, and filling empty tags
     */
    public function preprocess(?string $segment): string
    {
        $segment = $segment ?? '';

        $encoding = mb_detect_encoding($segment);
        $segment = mb_convert_encoding($segment, 'UTF-8', $encoding ?: 'UTF-8') ?: $segment;

        // Replace non-printable chars with placeholders (DOMDocument can't handle them)
        $segment = $this->replaceAscii($segment);

        // Do it again for entities
        $segment = $this->replaceHexEntities($segment);

        // Escape angle brackets that do not form a valid XLIFF tag so that plain-text
        // sequences like "<Expiry date symbol>" are not misinterpreted as malformed XML
        // (which would surface to the translator as a false-positive "Tag mismatch" error).
        $segment = $this->escapeStrayAngleBrackets($segment);

        // Fill empty HTML tags with placeholder to avoid contraction by saveXML()
        return $this->fillEmptyHTMLTagsWithPlaceholder($segment);
    }

    /**
     * Escapes angle brackets that are not part of a valid XLIFF tag.
     *
     * Valid XLIFF tags (see {@see self::XLIFF_TAG_REGEX}) are temporarily shielded with
     * placeholders, every remaining literal `<`/`>` is converted to its entity
     * (`&lt;`/`&gt;`), and the shielded tags are then restored untouched.
     *
     * This makes the QA DOM parsing resilient against plain-text segments that merely
     * look like markup (e.g. `<Expiry date symbol>`): such content is loaded as text
     * instead of failing XML parsing and being reported as a tag mismatch. Already
     * encoded entities (`&lt;`, `&gt;`) and genuine XLIFF tags are left unchanged, so
     * real tag errors are still detected.
     *
     * @param string $seg The segment to sanitize
     * @return string The segment with stray angle brackets escaped
     */
    public function escapeStrayAngleBrackets(string $seg): string
    {
        if (!str_contains($seg, '<') && !str_contains($seg, '>')) {
            return $seg;
        }

        $placeholders = [];
        $index = 0;

        // Shield valid XLIFF tags so the escaping step cannot touch them
        $shielded = preg_replace_callback(
            self::XLIFF_TAG_REGEX,
            function (array $matches) use (&$placeholders, &$index): string {
                $token = self::XLIFF_TAG_PLACEHOLDER_PREFIX . $index . self::XLIFF_TAG_PLACEHOLDER_SUFFIX;
                $placeholders[$token] = $matches[0];
                $index++;
                return $token;
            },
            $seg
        ) ?? $seg;

        // Escape every remaining (stray) angle bracket
        $shielded = str_replace(['<', '>'], ['&lt;', '&gt;'], $shielded);

        // Restore the shielded XLIFF tags
        return strtr($shielded, $placeholders);
    }

    /**
     * Replace non-printable ASCII characters with placeholders
     */
    public function replaceAscii(string $seg): string
    {
        preg_match_all(self::$regexpAscii, $seg, $matches);

        if (!empty($matches[1])) {
            $test_src = $seg;
            foreach ($matches[1] as $v) {
                $key = sprintf("%02X", ord($v));
                $test_src = preg_replace(
                    sprintf("/(\\x{%s}{1})/u", sprintf("%02X", ord($v))),
                    self::ASCII_PLACE_HOLD_MAP[$key]['placeHold'],
                    $test_src,
                    1
                ) ?? $test_src;
            }
            $seg = $test_src;
        }

        return $seg;
    }

    /**
     * Replaces hexadecimal HTML entities in a string with placeholders
     */
    public function replaceHexEntities(string $seg): string
    {
        preg_match_all(self::$regexpEntity, $seg, $matches);

        if (!empty($matches[1])) {
            $test_src = $seg;

            foreach ($matches[1] as $v) {
                $byte = sprintf("%02X", hexdec($v));

                if ($byte[0] == '0') {
                    $regexp = '/&#x([' . $byte[0] . ']?' . $byte[1] . ');/u';
                } else {
                    $regexp = '/&#x(' . $byte . ');/u';
                }

                $key = sprintf("%02X", hexdec($v));
                if (array_key_exists($key, self::ASCII_PLACE_HOLD_MAP)) {
                    $test_src = preg_replace($regexp, self::ASCII_PLACE_HOLD_MAP[$key]['placeHold'], $test_src) ?? $test_src;
                }
            }

            $seg = $test_src;
        }

        return $seg;
    }

    /**
     * Fill empty HTML tags with a placeholder to prevent contraction
     * Example: <g id="23"></g> would be contracted to <g ="23"/>
     */
    public function fillEmptyHTMLTagsWithPlaceholder(string $seg): string
    {
        preg_match_all('/<([^ >]+)[^>]*><\/\1>/', $seg, $matches);

        if (!empty($matches[0])) {
            foreach ($matches[0] as $match) {
                $parts = explode("><", $match);
                $replacedHtmlTag = $parts[0] . '>' . self::EMPTY_HTML_TAGS_PLACEHOLDER . '<' . $parts[1];
                $seg = str_replace($match, $replacedHtmlTag, $seg);
            }
        }

        return $seg;
    }

    /**
     * Clean output content by removing placeholders and restoring entities
     */
    public function cleanOutputContent(string $content): string
    {
        // Remove placeholder from empty HTML tags
        $content = str_replace(self::EMPTY_HTML_TAGS_PLACEHOLDER, '', $content);

        // Restore non-printable char placeholders to entities
        preg_match_all(self::$regexpPlaceHoldAscii, $content, $matches_trg);
        if (!empty($matches_trg[1])) {
            foreach ($matches_trg[1] as $v) {
                $content = preg_replace('/##\$_(' . $v . ')\$##/u', '&#x' . $v . ';', $content, 1) ?? $content;
            }
        }

        // Substitute 4(+)-byte characters from UTF-8 string to htmlentities
        $content = preg_replace_callback(
            '/([\xF0-\xF7]...)/s',
            [CatUtils::class, 'htmlentitiesFromUnicode'],
            $content
        ) ?? $content;

        // Re-encode control special characters
        $content = preg_replace('/\n/u', '&#10;', $content) ?? $content;
        $content = preg_replace('/\r/u', '&#13;', $content) ?? $content;
        $content = preg_replace('/\t/u', '&#09;', $content) ?? $content;
        // NBSP character (U+00A0)
        return preg_replace("/\xc2\xa0/u", '&#160;', $content) ?? $content;
    }
}
