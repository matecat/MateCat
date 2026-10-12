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
 *    is stored verbatim so that the URL stays visible, and so are markdown autolinks
 *    (`<https://…>`, `<name@example.com>`).
 *  - characters outside the Basic Multilingual Plane (emoji and other four-byte characters) and
 *    control characters other than tab and line breaks are removed.
 *
 * Which of the kept links a translator may follow is decided by the front end, through
 * `isAllowedLinkRedirect()` and `removeNotAllowedLinksFromHtml()`.
 *
 * Text nodes are never re-encoded: `Tom & Jerry` stays `Tom & Jerry`, because the value is also
 * read as plain text — `Uber::filterContributionStructOnSetTranslation()` regex-scrapes
 * `**Client:**` out of it — while every renderer injecting it as HTML runs `filterXSS` first.
 *
 * Because escaped markup has to be un-escaped before it can be stripped, entities are resolved
 * down to a fixed point: text that merely *looks* like a tag (`&amp;lt;script&amp;gt;`) is
 * stripped rather than stored, and a caller cannot smuggle markup through by escaping it twice.
 * Text meant to display a literal tag is therefore lost.
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
     * The only values a surviving `target` may hold, which rejects `target="_blank" onclick="…"`
     * breakout attempts.
     */
    private const ALLOWED_TARGETS = ['_blank', '_self', '_parent', '_top'];

    /**
     * Characters that make a `<` the start of markup for an HTML5 parser: a tag name, an end tag,
     * a comment or doctype (`!`) and a bogus comment (`?`).
     */
    private const MARKUP_START = '[a-zA-Z\/!?]';

    /**
     * Control characters except tab, line feed and carriage return.
     */
    private const CONTROL_CHARACTERS = '/[\x{0}-\x{8}\x{B}\x{C}\x{E}-\x{1F}\x{7F}]/u';

    private const ASTRAL_CHARACTERS = '/[\x{10000}-\x{10FFFF}]/u';

    /**
     * A markdown autolink (`<https://…>`, `<name@example.com>`): a scheme of two to 32 characters
     * or an email address, and no whitespace before the `>`. Word's `<o:p>` is not one.
     */
    private const AUTOLINK = '/<((?:[a-zA-Z][a-zA-Z0-9+.\-]{1,31}:|[^\s<>@\/]+@)[^\s<>]*)>/';

    /**
     * A browser reads an autolink as a tag, and each `/` in it as the start of an attribute. With
     * no `=` and no quote none of those attributes can carry a value.
     */
    private const ATTRIBUTE_VALUE = '/[=\'"]/';

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
        // inert by construction, and makes sanitize() idempotent.
        while ($sanitized !== $previous && $passes < self::MAX_PASSES) {
            $previous = $sanitized;
            $sanitized = self::stripOnce($sanitized);
            $passes++;
        }

        if ($sanitized !== $previous) {
            // Hit the cap without converging, so the last pass may have just decoded one more
            // level into something tag shaped. Never hand that back: at this point the anchors go
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
        // recognized as markup at all.
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Removed before parsing, so that a character removed from between `<` and a tag name
        // cannot join them into markup after the parser has run.
        $html = (string)preg_replace([self::CONTROL_CHARACTERS, self::ASTRAL_CHARACTERS], '', $html);

        if (trim($html) === '') {
            return '';
        }

        $html = self::alignWithHtml5($html);

        $htmlDom = new DOMDocument('1.0', 'UTF-8');
        $htmlDom->formatOutput = false;

        @$htmlDom->loadHTML('<?xml encoding="UTF-8">' . $html);

        $documentElement = $htmlDom->documentElement;

        if ($documentElement === null) {
            // Nothing the parser recognized as an element, e.g. a lone comment. Strip every tag and
            // give back the `<` that alignWithHtml5() escaped.
            return str_replace('&lt;', '<', strip_tags($html));
        }

        // Serialize the DOM by hand so that text nodes keep their characters as they are:
        // `Tom & Jerry` stays `Tom & Jerry`.
        return self::serialize($documentElement);
    }

    /**
     * Rewrite the input so that libxml reads it the way a browser does:
     *
     *  - a `<` that cannot start markup is escaped: libxml up to 2.9.13 would otherwise drop
     *    the text up to the next `>` (`5 < 6 > 2`)
     *  - a markdown autolink is kept as text; one that could carry an attribute value loses its
     *    brackets, so that its URL stays readable but is no longer tag shaped
     *  - comments an HTML5 parser closes early (`<!-->`, `<!--->`, `--!>`) are closed early here
     *    too, so that what follows them is parsed, and stripped, as markup
     *
     * @param string $html
     *
     * @return string
     */
    private static function alignWithHtml5(string $html): string
    {
        $html = str_replace(['<!--->', '<!-->', '--!>'], ['<!---->', '<!---->', '-->'], $html);

        $html = (string)preg_replace_callback(
            self::AUTOLINK,
            fn(array $autolink): string => preg_match(self::ATTRIBUTE_VALUE, $autolink[1]) === 1
                ? $autolink[1]
                : '&lt;' . $autolink[1] . '>',
            $html
        );

        return (string)preg_replace('/<(?!' . self::MARKUP_START . ')/', '&lt;', $html);
    }

    /**
     * Emit the subtree keeping only what the contract allows: text verbatim, anchors as anchors,
     * `<img>` as its bare src, `<script>`/`<style>` bodies dropped whole, and every other element
     * unwrapped to its children.
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

        $target = strtolower(self::removeQuoting($anchor->getAttribute('target')));

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
     * Remove the quotes and backslashes glued to an attribute value by payloads that deliver
     * their attributes JSON escaped (`href=\"…\"`), see StripTagsPreservingHrefsTest. A URL and a
     * target never need either.
     *
     * @param string $value
     *
     * @return string
     */
    private static function removeQuoting(string $value): string
    {
        return trim(str_replace(['\\', '"', "'"], '', $value));
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
     * @return string|null the URL without userinfo, or null when its scheme is not allowed or no
     *                     host is left
     */
    private static function sanitizeUrl(string $url): ?string
    {
        $url = self::removeQuoting($url);

        if ($url === '') {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (!is_string($scheme) || !in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true)) {
            return null;
        }

        if (strtolower($scheme) === 'mailto') {
            return $url;
        }

        // The front end allows a link by its host, and in `https://trusted.example@other.example/`
        // the host is the part after the last `@` of the authority.
        $url = (string)preg_replace('#^([a-zA-Z][a-zA-Z0-9+.\-]*://)[^/?\#]*@#', '$1', $url);

        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://[^/?\#]#', $url) !== 1) {
            return null;
        }

        return $url;
    }
}
