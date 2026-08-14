<?php

declare(strict_types=1);

namespace Utils\Email;

/**
 * Rewrites anything a mail client would turn into a link so that it stays text.
 *
 * MateCat emits no anchor around a team name — the templates write it as text and escape it — but
 * that is not where the link comes from. Mail clients linkify bare hostnames in body text
 * themselves. Measured in Apple Mail on 2026-08-13: a team named `evil.com` rendered clickable, and
 * so did an ordinary company name ending in `.com`; `Alpha.Beta` did not, because the client's
 * heuristic is TLD-aware. The surrounding double quotes in the template do not suppress it.
 *
 * Defanging is done here rather than by refusing such names at write time, because refusing them is
 * wrong far more often than it is right: of the 120 stored names that a hostname rule rejects, 14
 * are attacks and 106 are real. Customers name a team after their own domain — the creator's email
 * domain matches the team name in nine of them — and about twenty teams are named after a member's
 * address.
 *
 * A zero-width break was tried first, since it would have left the name looking untouched. It is
 * not enough: mail-tester stripped it and resolved `http://evil.com` anyway, which means a
 * link-rewriting gateway could do the same and hand the reader a clickable link where the client
 * had refused to make one. A bracket is a real character and no parser reassembles it.
 */
final class LinkDefanger
{
    /**
     * The separator a reader will see as a dot. Entity forms are matched because emails escape with
     * `double_encode: false`, so entity text reaches the recipient and their mail client turns it
     * back into a dot — `evil&#46;com` arrives as `evil.com`.
     */
    private const string DOT = '(?:\.|&#0*46;|&#[xX]0*2[eE];|&period;)';

    /**
     * A hostname label: starts and ends on a letter or digit, hyphens only inside.
     *
     * Written as alternating runs rather than as `[a-z0-9](?:[a-z0-9-]*[a-z0-9])?`, which describes
     * the same labels but ambiguously: for a run of n characters that form can split into a body and
     * a final character in n ways, and the engine tries them all before giving up. Runs of letters
     * and runs of hyphens can only be divided one way, and the possessive quantifiers say so — there
     * is no alternative division to return to, so the engine is told not to keep one.
     *
     * This is the smaller half of the cost. Measured on 2026-08-14: it made a 12KB dotless string
     * about four times faster, while the lookbehind below made it four thousand times faster.
     */
    private const string LABEL = '[a-z0-9]++(?:-++[a-z0-9]++)*+';

    public static function defang(string $text): string
    {
        $host    = '(?:' . self::LABEL . self::DOT . ')+[a-z]{2,}';
        $address = '[a-z0-9._%+-]+@' . $host;

        // Addresses first, so an address is recognised whole. Matching the host alone would leave
        // half of one rewritten — `f.surname@example.com` losing the dot before the `@` but
        // keeping the one after it — which is worse than either leaving it or bracketing all of it.
        //
        // The lookbehind is what keeps this linear. Without it a hostname could begin at any offset,
        // so a long run of letters with no dot in it — a comment body, which unlike a team name has
        // no length cap — was rescanned from every character in turn and cost time proportional to
        // the square of its length: 180ms for 12KB, and some ten seconds for 100KB. A hostname can
        // only start where a label starts, and saying so leaves one starting point instead of n. A
        // dot is deliberately not excluded here, or `.evil.com` would never be examined at all.
        $pattern = '~(?<![a-z0-9-])(?:(?<address>' . $address . ')|(?<scheme>[a-z][a-z0-9+.-]*://)?(?<host>' . $host . '))~i';

        $defanged = preg_replace_callback($pattern, static function (array $matches): string {
            // An address is left exactly as written. The worst a client makes of it is a mailto,
            // which opens a compose window rather than navigating anywhere, and roughly twenty real
            // teams are named after a member's address.
            if (($matches['address'] ?? '') !== '') {
                return $matches['address'];
            }

            $scheme = $matches['scheme'] ?? '';

            // The colon rather than the scheme's letters: it neutralises every scheme with one
            // rule, including `javascript:` and `data:`, where rewriting `http` to `hXXp` would
            // have covered three and left the rest live.
            if ($scheme !== '') {
                $scheme = str_replace('://', '[:]//', $scheme);
            }

            // The alternation guarantees one branch matched, but not to a static analyser: an
            // unmatched named group is absent from the array rather than empty.
            return $scheme . preg_replace('~' . self::DOT . '~i', '[.]', $matches['host'] ?? $matches[0]);
        }, $text);

        // preg_replace_callback returns null when PCRE gives up — the backtrack or recursion limit,
        // reachable on pathological input however linear the common case is. Casting that to a
        // string would yield an empty one, quietly deleting the value from the email instead of
        // failing to defang it. The text is returned undefanged instead: still escaped by the caller,
        // and visibly present.
        return $defanged ?? $text;
    }
}
