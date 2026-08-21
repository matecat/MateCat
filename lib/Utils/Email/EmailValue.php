<?php

declare(strict_types=1);

namespace Utils\Email;

use ArrayAccess;
use IteratorAggregate;
use Stringable;
use Traversable;

/**
 * A template value that escapes itself when written into an email.
 *
 * The email templates are plain PHP rendered by `extract()` and `include()`, so escaping was a thing
 * each `<?=` had to remember. It was remembered seven times out of eighty-seven: on 2026-08-13 every
 * interpolation across `lib/View/Emails` was counted, and the only escaped ones were the team names
 * added by `d2d207f880`. That is not a series of oversights, it is the predictable outcome of a rule
 * a template author has to apply by hand — the eighty-eighth interpolation would have been raw too.
 *
 * So the templates no longer decide. {@see AbstractEmail::_buildMessageContent()} wraps the values
 * before extracting them, and a value only reaches the page escaped. Arrays are wrapped as they are
 * traversed, so `$sender['first_name']` and `$team['name']` are covered without the template
 * knowing.
 *
 * Escaping happens on read rather than on construction. A template that needs the underlying string
 * — to measure it, compare it, or pass it on — gets the real one from {@see value()}, and only the
 * text written into the message is transformed.
 *
 * `raw()` is the way out, for values that are already markup. There is one today: the rendered
 * message body the layout embeds. Every use is asserted against an allowlist by
 * `EmailTemplateEscapingTest`, so adding another is a deliberate act rather than a quiet one.
 *
 * @implements ArrayAccess<array-key, mixed>
 * @implements IteratorAggregate<array-key, mixed>
 */
final class EmailValue implements Stringable, ArrayAccess, IteratorAggregate
{
    /**
     * ENT_QUOTES covers both quote characters, since a value can land inside an attribute as easily
     * as in text — `href` and `alt` both appear in these templates. ENT_SUBSTITUTE replaces invalid
     * UTF-8 with U+FFFD rather than returning an empty string, so a mangled byte cannot silently
     * blank out a whole interpolation.
     */
    private const int FLAGS = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5;

    /**
     * A value carries the two treatments it is to receive, so every combination is named at the call
     * site rather than inferred from where the value came from.
     *
     * @param bool $escapes runs htmlspecialchars, so the value cannot become markup
     * @param bool $defangs runs {@see LinkDefanger}, so the value cannot become a link
     * @param list<string> $verbatimKeys key names, at any depth, that are not defanged
     */
    private function __construct(
        private readonly mixed $value,
        private readonly bool $escapes = true,
        private readonly bool $defangs = true,
        private readonly array $verbatimKeys = []
    ) {
    }

    /**
     * Wraps every value in a template's variable array.
     *
     * `$verbatimKeys` names the values a reader must receive intact — the links the email exists to
     * offer, and the addresses it is about. Everything else is defanged, which is the way round that
     * fails loudly: forget to list a new link field and the button visibly breaks the first time
     * anyone renders that email, where forgetting to list a new *text* field would have meant the
     * defanging silently never ran.
     *
     * The exemption is by key name rather than by what the value looks like, so attacker-written
     * content cannot exempt itself. A team named `evil@evil.com` arrives under `name`, not under
     * `email`, and is treated as the free text it is.
     *
     * @param array<string, mixed> $variables
     * @param list<string> $verbatimKeys
     *
     * @return array<string, mixed>
     */
    public static function wrapAll(array $variables, array $verbatimKeys = []): array
    {
        $wrapped = [];

        foreach ($variables as $key => $value) {
            $wrapped[$key] = $value instanceof self
                ? $value
                : new self(
                    $value,
                    escapes: true,
                    defangs: !in_array((string)$key, $verbatimKeys, true),
                    verbatimKeys: $verbatimKeys
                );
        }

        return $wrapped;
    }

    /**
     * Marks a value as already-rendered markup, to be written without escaping.
     */
    public static function raw(mixed $value): self
    {
        // Neither treatment: markup that is escaped shows the reader its own tags, and markup that
        // is defanged has the `href` inside it bracketed.
        return new self($value, escapes: false, defangs: false);
    }

    /**
     * A value written as given but still escaped: the escape hatch for something that must not be
     * defanged and is not covered by the key list.
     */
    public static function verbatim(mixed $value): self
    {
        return new self($value, escapes: true, defangs: false);
    }

    /**
     * The underlying value, unescaped. For templates that need to work with the value rather than
     * write it.
     */
    public function value(): mixed
    {
        return $this->value;
    }

    public function __toString(): string
    {
        if (!is_scalar($this->value) && !$this->value instanceof Stringable) {
            // An array or object written directly is a template bug, not something to escape around.
            // Emitting nothing keeps "Array" and a conversion notice out of the message.
            return '';
        }

        $string = (string)$this->value;

        // Before escaping, so the pattern reads the text a reader will see rather than a stream of
        // entities. {@see LinkDefanger} for why this happens at all.
        if ($this->defangs) {
            $string = LinkDefanger::defang($string);
        }

        // `double_encode: false` is deliberate and load-bearing. Names written before the column
        // stored raw text are still entity-encoded in the database, and encoding them again shows
        // the reader "&amp;lt;" where they wrote "<". The cost is that entity text passes through to
        // the recipient, whose mail client decodes it — which is why callers that need to judge a
        // value, rather than print it, decode first: see TeamsController::assertNameIsPlainText().
        // Retiring this needs the column migrated, which the backlog tracks separately.
        return $this->escapes ? htmlspecialchars($string, self::FLAGS, 'UTF-8', false) : $string;
    }

    /**
     * Nested values are wrapped as they are reached, so `$sender['first_name']` escapes itself the
     * same way a top-level value does. Wrapping lazily rather than up front keeps a deep structure
     * from being walked when a template reads one key of it.
     */
    public function offsetGet(mixed $offset): mixed
    {
        $value = is_array($this->value) ? ($this->value[$offset] ?? null) : null;

        if ($value instanceof self) {
            return $value;
        }

        // The key list applies at any depth: `user['email']` and `commenter['email']` reach the page
        // through a `toArray()` nobody restructured for this, and a reader must see their own
        // address whole.
        $defangs = $this->defangs && !in_array((string)$offset, $this->verbatimKeys, true);

        return new self($value, $this->escapes, $defangs, $this->verbatimKeys);
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_array($this->value) && isset($this->value[$offset]);
    }

    /**
     * Templates read; they do not assign. Writing through the wrapper would produce a value nobody
     * had decided the escaping of.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
    }

    public function offsetUnset(mixed $offset): void
    {
    }

    /**
     * Lets a template loop a wrapped list — `foreach ($rows as $row)` — and still get wrapped
     * members rather than raw ones.
     *
     * @return Traversable<array-key, mixed>
     */
    public function getIterator(): Traversable
    {
        foreach (is_array($this->value) ? $this->value : [] as $key => $value) {
            yield $key => ($value instanceof self ? $value : $this->offsetGet($key));
        }
    }
}
