<?php

namespace Utils\Tools;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitizes the `instructions` strings coming from third party integrations.
 *
 * The contract, identical for file and file_part instructions, is:
 *
 *  - every HTML tag is stripped, `<a>` excepted: an anchor whose URL passes the scheme allowlist
 *    survives as an anchor, reduced to its safe attributes, so
 *    `This is a nested <a href="http://test.com" target="_blank" onclick="…">link</a>` becomes
 *    `This is a nested <a href="http://test.com" target="_blank">link</a>`
 *  - markdown is left untouched, hyperlinks included: `This is a nested [link](http://test.com)`
 *    is stored verbatim so that the URL stays visible
 *
 * The anchor is *kept*, not flattened to `label (url)`: which links a translator may actually
 * follow is a rendering decision, and the front end already owns it — `isAllowedLinkRedirect()`
 * plus `removeNotAllowedLinksFromHtml()` flatten whatever the current deployment disallows (core
 * disallows everything, `plugins/translated` allows its own domains). Flattening here would take
 * that decision away from the only layer that can make it, and would also diverge from the shape
 * every stored row has had so far.
 *
 * Text nodes are never re-encoded: the stored value is deliberately neither strict HTML nor strict
 * plain text. `Tom & Jerry` stays `Tom & Jerry`, because the value is also read as plain text —
 * `Uber::filterContributionStructOnSetTranslation()` regex-scrapes `**Client:**` out of it — while
 * every renderer injecting it as HTML runs `filterXSS` first. Escaping belongs to the rendering
 * layer, not to the storage layer.
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
     * The only values a surviving `target` may hold. An allowlist of values rather than escaping:
     * it rejects both `target="_blank" onclick="…"` breakout attempts and the stray backslash that
     * payloads delivering their attributes as `target=\"_blank\"` would otherwise leave behind.
     */
    private const ALLOWED_TARGETS = ['_blank', '_self', '_parent', '_top'];

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
            // level into something tag shaped. Never hand that back — at this point the anchors go
            // too, which is the right trade for a payload this pathological.
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

        // Walk the DOM rather than calling saveHtml() and running strip_tags() over the result:
        // saveHtml() re-encodes every entity, so `Tom & Jerry` would come back as `Tom &amp; Jerry`
        // and corrupt every text consumer of the stored value.
        return self::serialize($documentElement);
    }

    /**
     * Emit the subtree keeping only what the contract allows: text verbatim, anchors as anchors,
     * `<img>` as its bare src, `<script>`/`<style>` bodies dropped whole — stripping those two tags
     * alone would leave their body behind as visible text — and every other element unwrapped to
     * its children.
     *
     * @param DOMNode $node
     *
     * @return string
     */
    private static function serialize(DOMNode $node): string
    {
        $serialized = '';

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $serialized .= (string)$child->nodeValue;

                continue;
            }

            // Comments, processing instructions and the doctype carry nothing worth keeping.
            if (!$child instanceof DOMElement) {
                continue;
            }

            $tagName = strtolower($child->tagName);

            if (in_array($tagName, self::DROPPED_SUBTREES, true)) {
                continue;
            }

            if ($tagName === 'a') {
                $serialized .= self::serializeAnchor($child);

                continue;
            }

            if ($tagName === 'img') {
                $serialized .= (string)self::sanitizeUrl($child->getAttribute('src'));

                continue;
            }

            $serialized .= self::serialize($child);
        }

        return $serialized;
    }

    /**
     * Emit an anchor reduced to its safe attributes, or just its label when the URL is not usable.
     *
     * Nested anchors cannot survive as nested: the HTML parser has already hoisted them into
     * siblings, which is what keeps the output re-parseable to the same tree.
     *
     * @param DOMElement $anchor
     *
     * @return string
     */
    private static function serializeAnchor(DOMElement $anchor): string
    {
        $label = self::serialize($anchor);
        $href = self::sanitizeUrl($anchor->getAttribute('href'));

        if ($href === null) {
            return $label;
        }

        $attributes = ' href="' . self::escapeAttribute($href) . '"';

        $target = strtolower(trim($anchor->getAttribute('target')));

        if (in_array($target, self::ALLOWED_TARGETS, true)) {
            $attributes .= ' target="' . $target . '"';
        }

        $title = trim($anchor->getAttribute('title'));

        if ($title !== '') {
            $attributes .= ' title="' . self::escapeAttribute($title) . '"';
        }

        // A label-less anchor would render as a hole in the text: show the URL instead.
        if (trim($label) === '') {
            $label = self::escapeAttribute($href);
        }

        return '<a' . $attributes . '>' . $label . '</a>';
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private static function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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
     * @param string $url
     *
     * @return string|null the URL, or null when its scheme is not allowed
     */
    private static function sanitizeUrl(string $url): ?string
    {
        // Historically hrefs arrive with escaped quotes glued to them, see the JSON payloads in
        // StripTagsPreservingHrefsTest. Backslashes go whole: a URL never needs one, they are the
        // residue those payloads leave behind, and browsers normalize them to a slash.
        $url = trim(str_replace(['\\', '"', "'"], '', $url));

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
