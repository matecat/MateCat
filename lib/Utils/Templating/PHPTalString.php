<?php

namespace Utils\Templating;

use Stringable;

/**
 * Wraps a string for interpolation into a JavaScript literal inside a PHPTAL
 * <script> block.
 *
 * PHPTAL does not escape `${...}` interpolations placed inside a <script>
 * element: the value is emitted verbatim. A template that supplies its own
 * quotes — `name: '${value}'` — therefore lets any apostrophe in the value close
 * the literal and append arbitrary JavaScript, and any `</script>` in the value
 * close the element.
 *
 * This class renders the value as a complete JSON string literal, quotes
 * included, so the template must NOT add quotes of its own:
 *
 *     name: ${value},
 *
 * `<`, `>`, `&`, `'` and `"` are emitted as `\uXXXX` escapes, as is every
 * non-ASCII character — which also neutralises U+2028 and U+2029, valid JSON but
 * literal line terminators inside a JavaScript string before ES2019.
 *
 * @see PHPTalMap for the same contract over an array, and PHPTalBoolean over a bool.
 */
class PHPTalString implements Stringable
{

    private const int FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

    private string $value;

    public function __construct(?string $value)
    {
        $this->value = $value ?? '';
    }

    public function __toString(): string
    {
        $encoded = json_encode($this->value, self::FLAGS);

        // json_encode only fails here on malformed UTF-8; an empty literal is a safe
        // fallback because the template relies on this value carrying its own quotes.
        return is_string($encoded) ? $encoded : '""';
    }

}
