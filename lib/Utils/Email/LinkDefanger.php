<?php

declare(strict_types=1);

namespace Utils\Email;

/**
 * Rewrites anything a mail client would turn into a link so that it stays text.
 *
 * MateCat emits no anchor around a team name; the templates write it as text and escape it. But
 * that is not where the link comes from. Mail clients linkify bare hostnames in body text
 * themselves. Measured in Apple Mail on 2026-08-13: a team named `evil.com` rendered clickable, and
 * so did an ordinary company name ending in `.com`; `Alpha.Beta` did not, because the client's
 * heuristic is TLD-aware. The surrounding double quotes in the template do not suppress it.
 *
 * Defanging is done here rather than by refusing such names at write time, because refusing them is
 * wrong far more often than it is right: of the 120 stored names that a hostname rule rejects, 14
 * are attacks and 106 are real. Customers name a team after their own domain (the creator's email
 * domain matches the team name in nine of them) and about twenty teams are named after a member's
 * address.
 *
 * A zero-width break was tried first, since it would have left the name looking untouched. It is
 * not enough: mail-tester stripped it and resolved `http://evil.com` anyway, which means a
 * link-rewriting gateway could do the same and hand the reader a clickable link where the client
 * had refused to make one. A bracket is a real character and no parser reassembles it.
 *
 * Two rules, applied one after the other:
 *
 * 1. Every scheme separator is broken, whatever follows it: `https://` becomes `https[:]//`. A
 *    browser resolves far more hosts than a pattern can list (`http://3405803781` is 203.0.113.5,
 *    `https://evil。com` is evil.com, `https://support@evil.com` is evil.com), and a client links
 *    any of them once a scheme is in front. Breaking the separator covers them all at once.
 * 2. A bare hostname, which clients link without a scheme, has its dots bracketed: `evil.com`
 *    becomes `evil[.]com`. Labels may be in any script, so `матекат.рф` is caught too, and an
 *    invisible character inside a label (a zero-width space, a soft hyphen) does not split it.
 *
 * Ideographic and fullwidth dots are not treated as dots for a bare hostname: `。` is the full stop
 * of Chinese and Japanese prose, and bracketing every sentence would be a cost with no measured
 * benefit. After a scheme they are harmless, because rule 1 has already broken the link.
 */
final class LinkDefanger
{
    /**
     * The separator a reader will see as a dot. Entity forms are matched as well as the character
     * because a caller decodes once before defanging, and one pass does not flatten text that was
     * encoded twice: `evil&amp;#46;com` decodes to `evil&#46;com`, which the recipient's mail
     * client would turn back into a dot. Matching the entity forms keeps the rule from depending on
     * how many passes ran.
     *
     * Atomic, so a long chain of labels leaves no alternative to return to at each dot.
     */
    private const string DOT = '(?>\.|&#0*46;|&#[xX]0*2[eE];|&period;)';

    /**
     * A character of a hostname label: a letter or digit in any script, a combining mark, or an
     * invisible character a browser drops when it reads the host (format characters such as
     * U+200B, and the soft hyphen U+00AD).
     */
    private const string LABEL_CHAR = '[\p{L}\p{N}\p{M}\p{Cf}\x{AD}]';

    /**
     * A hostname label: runs of label characters, hyphens only inside.
     *
     * Alternating possessive runs rather than `[a-z0-9](?:[a-z0-9-]*[a-z0-9])?`, which describes the
     * same labels ambiguously: for a run of n characters that form can split into a body and a final
     * character in n ways, and the engine tries them all before giving up.
     */
    private const string LABEL = self::LABEL_CHAR . '++(?:-++' . self::LABEL_CHAR . '++)*+';

    /**
     * A scheme separator: a colon after a scheme name, followed by two slashes or backslashes in any
     * mix (browsers read `http:\\evil.com` as `http://evil.com`). The scheme name has at least two
     * characters, so a Windows drive letter (`C:\\server`) is left alone.
     */
    private const string SCHEME_SEPARATOR = '~(?<=[a-z0-9+.-][a-z0-9+.-]):(?=[/\\\\]{2})~i';

    public static function defang(string $text): string
    {
        $schemeFree = preg_replace(self::SCHEME_SEPARATOR, '[:]', $text);
        if ($schemeFree === null) {
            return self::defangEverything($text);
        }

        $host = self::LABEL . '(?:' . self::DOT . self::LABEL . ')++';

        // An address must not start where another address could have started one character
        // earlier, and its local part is possessive: together they keep a long run of dots or
        // underscores linear, where each position used to start a new attempt.
        //
        // An address right after a slash is the user part of a URL (`https://support@evil.com`),
        // and is read as a hostname instead, so the host after the `@` is defanged.
        $address = '(?<![a-z0-9._%+-])(?<!/)[a-z0-9._%+-]++@' . $host;

        // A hostname can only start where a label starts. A dot is deliberately not excluded, or
        // `.evil.com` would never be examined at all.
        $pattern = '~(?<address>' . $address . ')|(?<![\p{L}\p{N}\p{M}\p{Cf}\x{AD}-])(?<host>' . $host . ')~iu';

        $defanged = preg_replace_callback($pattern, static function (array $matches): string {
            // An address is left exactly as written. The worst a client makes of it is a mailto,
            // which opens a compose window rather than navigating anywhere, and roughly twenty real
            // teams are named after a member's address.
            if ($matches['address'] !== '') {
                return $matches['address'];
            }

            return self::bracketHost($matches['host'] ?? $matches[0]);
        }, $schemeFree);

        // PCRE gives up on pathological input (the JIT stack, the backtrack or recursion limit, or
        // text that is not valid UTF-8) by returning null. The value then has every dot and scheme
        // broken: an email that shows too many brackets is a cost, a live link is not.
        return $defanged ?? self::defangEverything($schemeFree);
    }

    /**
     * Brackets the dots of a dotted name whose last label could be a top-level domain.
     *
     * The name matched is the whole dotted run, so its last label may not be a domain suffix:
     * `S.r.l` ends in a single letter and `1.2.3` in a digit. Labels are dropped from the end until
     * one starts with two letters, which is what a suffix needs; when none does, the text is kept.
     */
    private static function bracketHost(string $host): string
    {
        $parts = preg_split('~(' . self::DOT . ')~iu', $host, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return self::defangEverything($host);
        }

        // $parts alternates label, dot, label, dot, ..., label.
        for ($last = count($parts) - 1; $last >= 2; $last -= 2) {
            $suffix = preg_replace('~[\p{Cf}\x{AD}]~u', '', $parts[$last]) ?? '';

            if (preg_match('~^\p{L}{2}~u', $suffix) === 1) {
                $kept = '';
                for ($i = 0; $i <= $last; $i++) {
                    $kept .= ($i % 2 === 1) ? '[.]' : $parts[$i];
                }

                return $kept . implode('', array_slice($parts, $last + 1));
            }
        }

        return $host;
    }

    /**
     * The fallback when a pattern cannot run: every scheme separator and every dot is broken.
     */
    private static function defangEverything(string $text): string
    {
        // strtr tries the longest key first, so a separator or a dot already broken by an earlier
        // pass is kept as it is instead of being bracketed twice.
        return strtr($text, [
            '[:]' => '[:]',
            '[.]' => '[.]',
            '://' => '[:]//',
            ':\\\\' => '[:]\\\\',
            ':/\\' => '[:]/\\',
            ':\\/' => '[:]\\/',
            '.' => '[.]',
            '&#46;' => '[.]',
            '&#x2e;' => '[.]',
            '&#x2E;' => '[.]',
            '&#X2e;' => '[.]',
            '&#X2E;' => '[.]',
            '&period;' => '[.]',
        ]);
    }
}
