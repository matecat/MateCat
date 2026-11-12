<?php

declare(strict_types=1);

namespace Utils\Email;

use LogicException;
use Stringable;

/**
 * Escapes the variables of an email template once, before the template runs.
 *
 * The email templates are plain PHP rendered by `extract()` and `include()`. Escaping by hand in each
 * `<?=` was remembered seven times out of eighty-seven (counted on 2026-08-13), so templates no longer
 * decide: {@see AbstractEmail::_buildMessageContent()} passes the variables through {@see escapeAll()}.
 *
 * What a template receives is a plain PHP value, so `if`, `empty()`, `count()`, `==`, `strtotime()`
 * and `DateTime::createFromFormat()` behave as they do for any value. Example: a team named
 * `Ben & Jerry <b>` arrives as the string `Ben &amp; Jerry &lt;b&gt;`, and `false` arrives as `false`.
 *
 * The one value that is markup, the rendered body the layout embeds, is set by
 * {@see AbstractEmail::_buildHTMLMessage()} after this runs.
 */
final class EmailValue
{
    /**
     * ENT_QUOTES covers both quote characters, since a value can land inside an attribute as easily
     * as in text: `href` and `alt` both appear in these templates. ENT_SUBSTITUTE replaces invalid
     * UTF-8 with U+FFFD rather than returning an empty string, so a mangled byte cannot silently
     * blank out a whole interpolation.
     */
    private const int FLAGS = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5;

    /**
     * Escapes every value of a template's variable array, at any depth.
     *
     * - a string is escaped, and defanged unless its key is in `$verbatimKeys`;
     * - an array is walked the same way, with its keys kept;
     * - `null`, booleans, integers and floats are kept as they are: none can carry markup;
     * - a `Stringable` object is escaped as its string;
     * - any other object throws, so a struct can never reach a template unescaped.
     *
     * `$verbatimKeys` names the values a reader must receive intact: the links the email exists to
     * offer, and the addresses it is about. Everything else is defanged, which is the way round that
     * fails loudly: forget to list a new link field and the button visibly breaks the first time
     * anyone renders that email, where forgetting to list a new *text* field would have meant the
     * defanging silently never ran. The key list applies at any depth, so `user['email']` and
     * `commenter['email']` keep their address whole.
     *
     * The exemption is by key name rather than by what the value looks like, so attacker-written
     * content cannot exempt itself. A team named `evil@evil.com` arrives under `name`, not under
     * `email`, and is treated as the free text it is.
     *
     * @param array<array-key, mixed> $variables
     * @param list<string> $verbatimKeys
     *
     * @return array<array-key, mixed>
     *
     * @throws LogicException when a value is an object that cannot be written as text
     */
    public static function escapeAll(array $variables, array $verbatimKeys = []): array
    {
        return self::escapeArray($variables, true, $verbatimKeys, '');
    }

    /**
     * @param array<array-key, mixed> $values
     * @param list<string> $verbatimKeys
     *
     * @return array<array-key, mixed>
     *
     * @throws LogicException
     */
    private static function escapeArray(array $values, bool $defangs, array $verbatimKeys, string $path): array
    {
        $escaped = [];

        foreach ($values as $key => $value) {
            $escaped[$key] = self::escapeValue(
                $value,
                $defangs && !in_array((string)$key, $verbatimKeys, true),
                $verbatimKeys,
                $path === '' ? (string)$key : $path . '.' . $key
            );
        }

        return $escaped;
    }

    /**
     * @param list<string> $verbatimKeys
     *
     * @throws LogicException
     */
    private static function escapeValue(mixed $value, bool $defangs, array $verbatimKeys, string $path): mixed
    {
        return match (true) {
            is_string($value) => self::escapeString($value, $defangs),
            $value instanceof Stringable => self::escapeString((string)$value, $defangs),
            is_array($value) => self::escapeArray($value, $defangs, $verbatimKeys, $path),
            $value === null, is_bool($value), is_int($value), is_float($value) => $value,
            default => throw new LogicException(
                "Email template variable '$path' is a " . get_debug_type($value) . ', which cannot be escaped.'
                . ' Pass an array or a scalar'
            ),
        };
    }

    private static function escapeString(string $string, bool $defangs): string
    {
        // Both treatments read the text the reader will end up seeing, which is the decoded form:
        // the recipient's mail client decodes entities whatever this class does, so judging
        // `evil&#46;com` as if it were literal text is judging something nobody is shown. Decoding
        // first and escaping the result afterwards is what makes the two agree. The same reasoning
        // is why callers that judge a value rather than print it decode first: see
        // {@see \Utils\Validation\UserSuppliedName}.
        $string = html_entity_decode($string, self::FLAGS, 'UTF-8');

        // After decoding, so the pattern reads a hostname rather than a stream of entities.
        // {@see LinkDefanger} for why this happens at all.
        if ($defangs) {
            $string = LinkDefanger::defang($string);
        }

        // `double_encode` keeps its default of true, so an `&` that survived the decode cannot reach
        // the recipient still looking like the start of an entity. Names stored before the column
        // held raw text were entity-encoded and needed the flag off to render; that population was
        // decoded in the database on 2026-08-26, and what is left is a handful of XSS test payloads
        // which are meant to read as text.
        return htmlspecialchars($string, self::FLAGS, 'UTF-8');
    }
}
