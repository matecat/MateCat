<?php

namespace Utils\Tools;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitizes the `instructions` strings coming from third party integrations.
 *
 * The contract, identical for file/file_part instructions and for segment level instructions, is:
 *
 *  - every HTML tag is stripped, `<a>` excepted: for anchors the URL is extracted and shown right
 *    after the label, so `This is a nested <a href="http://test.com">link</a>` becomes
 *    `This is a nested link (http://test.com)`
 *  - markdown is left untouched, hyperlinks included: `This is a nested [link](http://test.com)`
 *    is stored verbatim so that the URL stays visible
 *
 * The returned value is plain text: HTML entities are resolved, never re-introduced. Escaping
 * belongs to the rendering layer, not to the storage layer.
 *
 * Because escaped markup has to be un-escaped before it can be stripped, entities are resolved
 * down to a fixed point: text that merely *looks* like a tag (`&amp;lt;script&amp;gt;`) is
 * stripped rather than stored, and a caller cannot smuggle markup through by escaping it twice.
 * Text meant to display a literal tag is therefore lost — consistent with the contract, which is
 * to strip HTML.
 *
 * This is deliberately separate from {@see Utils::stripTagsPreservingHrefs()}, which keeps serving
 * XLIFF segment notes with its own (markdown-link shaped) output format.
 */
class InstructionsSanitizer
{
    /**
     * Encodings probed, in order, when the input is not valid UTF-8.
     */
    private const SOURCE_ENCODINGS = ['UTF-8', 'Windows-1252', 'ISO-8859-1'];

    /**
     * Upper bound on the decode-and-strip passes, so that a pathological payload cannot spin.
     */
    private const MAX_PASSES = 5;

    /**
     * URL schemes allowed to survive in an href/src. Anything else (javascript:, data:,
     * vbscript:, relative and protocol relative URLs) is dropped.
     */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * Tags whose whole subtree is removed: stripping the tags alone would leave their body
     * behind as visible text.
     */
    private const DROPPED_SUBTREES = ['script', 'style'];

    /**
     * @param string $raw
     *
     * @return string
     */
    public static function sanitize(string $raw): string
    {
        if (trim($raw) === '') {
            return '';
        }

        $sanitized = self::toUtf8($raw);
        $previous = null;
        $passes = 0;

        // Run to a fixed point. One pass is not enough: decoding `&amp;lt;script&amp;gt;` yields
        // the text `<script>…`, which is markup a later pass — or any consumer injecting the
        // stored value as HTML — would act on. Looping until nothing changes leaves text that is
        // inert by construction, and makes sanitize() idempotent. Note this is not the classic
        // decode-until-stable bypass: that one decodes *after* filtering, resurrecting the markup
        // that was just removed, whereas here every pass re-filters what it decodes.
        while ($sanitized !== $previous && $passes < self::MAX_PASSES) {
            $previous = $sanitized;
            $sanitized = self::stripOnce($sanitized);
            $passes++;
        }

        if ($sanitized !== $previous) {
            // Hit the cap without converging, so the last pass may have just decoded one more
            // level into something tag shaped. Never hand that back.
            $sanitized = strip_tags($sanitized);
        }

        return trim($sanitized);
    }

    /**
     * One decode-and-strip pass.
     *
     * @param string $html
     *
     * @return string
     */
    private static function stripOnce(string $html): string
    {
        // Resolve the entities the integrations send: connectors such as TOS/Phrase deliver their
        // anchors HTML escaped (`&lt;a href="..."&gt;`), and without this they would never be
        // recognized as markup at all — the reported bug.
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (trim($html) === '') {
            return '';
        }

        $htmlDom = new DOMDocument('1.0', 'UTF-8');
        $htmlDom->formatOutput = false;

        @$htmlDom->loadHTML('<?xml encoding="UTF-8">' . $html);

        $documentElement = $htmlDom->documentElement;

        if ($documentElement === null) {
            return $html;
        }

        self::dropSubtrees($htmlDom);
        self::rewriteAnchors($htmlDom);
        self::rewriteImages($htmlDom);

        // Read the text straight off the DOM rather than serializing and running strip_tags():
        // saveHtml() re-encodes every entity, so `Tom & Jerry` would come back as `Tom &amp; Jerry`
        // and corrupt every text consumer of the stored value.
        return $documentElement->textContent;
    }

    /**
     * Normalize to UTF-8 declaring the source encoding, so that accented characters are converted
     * instead of being replaced by `?`.
     *
     * @param string $raw
     *
     * @return string
     */
    private static function toUtf8(string $raw): string
    {
        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        $detected = mb_detect_encoding($raw, self::SOURCE_ENCODINGS, true) ?: 'Windows-1252';

        return mb_convert_encoding($raw, 'UTF-8', $detected) ?: $raw;
    }

    /**
     * @param DOMDocument $htmlDom
     *
     * @return void
     */
    private static function dropSubtrees(DOMDocument $htmlDom): void
    {
        foreach (self::DROPPED_SUBTREES as $tagName) {
            $nodes = $htmlDom->getElementsByTagName($tagName);

            // Iterate backwards: the node list is live, removing while going forward skips nodes.
            for ($i = $nodes->length - 1; $i > -1; $i--) {
                $node = $nodes->item($i);

                if ($node instanceof DOMNode && $node->parentNode !== null) {
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

    /**
     * Replace every `<a>` with `label (url)`, or with the bare label when the URL is not usable.
     *
     * @param DOMDocument $htmlDom
     *
     * @return void
     */
    private static function rewriteAnchors(DOMDocument $htmlDom): void
    {
        $links = $htmlDom->getElementsByTagName('a');

        for ($i = $links->length - 1; $i > -1; $i--) {
            $link = $links->item($i);

            if (!$link instanceof DOMElement || $link->parentNode === null) {
                continue;
            }

            $label = trim((string)$link->nodeValue);
            $href = self::sanitizeUrl($link->getAttribute('href'));

            if ($href === null || $href === $label) {
                $replacement = $label !== '' ? $label : (string)$href;
            } elseif ($label === '') {
                $replacement = $href;
            } else {
                $replacement = $label . ' (' . $href . ')';
            }

            $link->parentNode->replaceChild($htmlDom->createTextNode($replacement), $link);
        }
    }

    /**
     * Replace every `<img>` with its src.
     *
     * @param DOMDocument $htmlDom
     *
     * @return void
     */
    private static function rewriteImages(DOMDocument $htmlDom): void
    {
        $images = $htmlDom->getElementsByTagName('img');

        for ($i = $images->length - 1; $i > -1; $i--) {
            $image = $images->item($i);

            if (!$image instanceof DOMElement || $image->parentNode === null) {
                continue;
            }

            $src = self::sanitizeUrl($image->getAttribute('src'));

            $image->parentNode->replaceChild($htmlDom->createTextNode((string)$src), $image);
        }
    }

    /**
     * @param string $url
     *
     * @return string|null the URL, or null when its scheme is not allowed
     */
    private static function sanitizeUrl(string $url): ?string
    {
        // Historically hrefs arrive with escaped quotes glued to them, see the JSON payloads in
        // StripTagsPreservingHrefsTest.
        $url = trim(str_replace(['\\"', '"', "'"], '', $url));

        if ($url === '') {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (!is_string($scheme) || !in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true)) {
            return null;
        }

        return $url;
    }
}
