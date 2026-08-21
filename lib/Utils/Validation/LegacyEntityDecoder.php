<?php

declare(strict_types=1);

namespace Utils\Validation;

/**
 * Undoes the encoding the old write filters left in a name column.
 *
 * Names used to be encoded at the write boundary by one `FILTER_SANITIZE_SPECIAL_CHARS` or another,
 * so a resource called "O'Brien" is stored as "O&#39;Brien". {@see UserSuppliedName} stores them as
 * typed now, and the read paths that used to decode have been removed — a `html_entity_decode` on
 * read turns a name genuinely called "A &amp; B" into "A & B" for good. That leaves the rows written
 * before the change to be decoded once, in place.
 *
 * Deliberately not `html_entity_decode`. Only the five numeric forms that filter produced are
 * decoded; the named forms are left exactly as they are, because that filter never wrote them, so an
 * `&amp;` in a column today is one somebody typed.
 */
final class LegacyEntityDecoder
{
    /**
     * `&#38;` is replaced **last**, and the order is the whole correctness argument.
     *
     * `&#38;#60;` is what a user who typed the six characters "&#60;" got: the filter encoded their
     * ampersand and left the rest alone. Decoding the ampersand first would produce "&#60;", which
     * the next rule would then turn into "<" — one level too far, and the name they typed is gone.
     * Decoding it last leaves "&#60;", which is what they wrote.
     *
     * PHP preserves insertion order here, and `str_replace` over an ordered map applies the pairs in
     * that order, so this array *is* the algorithm.
     */
    private const array ENCODED = [
        '&#60;' => '<',
        '&#62;' => '>',
        '&#34;' => '"',
        '&#39;' => "'",
        '&#38;' => '&',
    ];

    /**
     * Note what this cannot tell you: whether a value *should* be decoded. `&#60;` is both what the
     * old filter wrote for "<" and what it wrote for a user who typed "&#60;" — after one pass the
     * second is the correct value and decoding it again destroys it. Nothing in the string
     * distinguishes them, so the caller has to bound by when the row was written, not by what it
     * holds. `support_scripts/tasks/decode-legacy-memory-key-names.php` does that with `update_date`.
     */
    public static function decode(string $value): string
    {
        return str_replace(array_keys(self::ENCODED), array_values(self::ENCODED), $value);
    }
}
