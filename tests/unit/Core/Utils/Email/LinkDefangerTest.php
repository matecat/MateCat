<?php

declare(strict_types=1);

namespace Matecat\Core\Utils\Email;

use Matecat\TestHelpers\AbstractTest;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Utils\Email\EmailValue;
use Utils\Email\InvitedToTeamEmail;
use Utils\Email\LinkDefanger;

/**
 * Everything asserted here about what a client does was measured in Apple Mail on 2026-08-13 by
 * rendering the real template, saving the message and opening it, rather than assumed: `evil.com`
 * and an ordinary company name ending in `.com` were made clickable, `Alpha.Beta` was not, and all three neutralisations
 * tried were inert. Which is also why the false positives below are asserted rather than apologised
 * for — they are the price of a rule that cannot consult a registry.
 */
class LinkDefangerTest extends AbstractTest
{

    /**
     * @return array<string, array{string, string}>
     */
    public static function hostnames(): array
    {
        return [
            // The vector, and the reason any of this exists.
            'a bare hostname' => ['evil.com', 'evil[.]com'],
            'every label, not just one' => ['login.microsoftonline.com', 'login[.]microsoftonline[.]com'],
            'a hostname inside prose' => ['Verify at evil-login.co now', 'Verify at evil-login[.]co now'],

            // Entity forms. Callers decode once before defanging, and one pass does not flatten
            // text that was encoded twice, so a residual entity dot still has to be matched here
            // or the recipient's client turns it back into a live hostname.
            'a decimal entity dot' => ['evil&#46;com', 'evil[.]com'],
            'a hex entity dot' => ['evil&#x2E;com', 'evil[.]com'],
            'a named entity dot' => ['evil&period;com', 'evil[.]com'],

            // Schemes: the colon rather than the scheme's letters, so one rule covers all of them.
            'an http scheme' => ['https://evil.com', 'https[:]//evil[.]com'],
            'a path is kept' => ['https://evil.com/login?a=1', 'https[:]//evil[.]com/login?a=1'],
            'a javascript scheme' => ['javascript://evil.com', 'javascript[:]//evil[.]com'],

            // Addresses are recognised whole. Half-defanging one is worse than either choice made
            // consistently, and roughly twenty real teams are named after a member's address.
            'an address is untouched' => ['f.surname@example.com', 'f.surname@example.com'],
            'a long address' => ['student.if17@campus.edu.ua', 'student.if17@campus.edu.ua'],

            // The cost, asserted so it is visible here rather than discovered by a customer.
            'a real customer name' => ['Acme.com', 'Acme[.]com'],
            'a real customer in prose' => ['Acme.guru Polite statements', 'Acme[.]guru Polite statements'],

            // False positives Apple Mail would not have linkified. No shape-based rule can tell
            // these from a real company name ending in a live suffix, which it did.
            'a non-TLD suffix' => ['Alpha.Beta', 'Alpha[.]Beta'],
            'an abbreviation pair' => ['Ejercicio HH.CC Bilbo y Frodo', 'Ejercicio HH[.]CC Bilbo y Frodo'],

            // Left alone: a single trailing letter is not a suffix.
            'an abbreviation' => ['Translated S.r.l.', 'Translated S.r.l.'],
            'ordinary text' => ['Marketing Team', 'Marketing Team'],
        ];
    }

    #[Test]
    #[DataProvider('hostnames')]
    public function defangingRewritesWhatAClientWouldLinkify(string $input, string $expected): void
    {
        $this->assertSame($expected, LinkDefanger::defang($input));
    }

    /**
     * Applying it twice must not double-bracket, since a value can pass through more than one
     * rendering path.
     */
    #[Test]
    public function defangingIsIdempotent(): void
    {
        $once = LinkDefanger::defang('evil.com');

        $this->assertSame($once, LinkDefanger::defang($once));
    }

    /**
     * Cost has to stay proportional to the length of the text, not to its square.
     *
     * The pattern runs on every free-text value in an email, and a comment body has no length cap
     * the way a team name does. Before the start-boundary lookbehind, a hostname could begin at any
     * offset, so a long run of letters containing no dot was rescanned from every character: 12KB
     * took 180ms and 100KB would have taken seconds. Asserted as a ratio between two sizes rather
     * than as a millisecond budget, so it measures the shape of the growth and not the machine.
     */
    #[Test]
    public function defangingCostGrowsWithLengthAndNotWithItsSquare(): void
    {
        $measure = static function (int $length): float {
            $text = str_repeat('a', $length);
            LinkDefanger::defang($text);                      // warm, so the first call pays no setup

            $started = hrtime(true);
            for ($i = 0; $i < 20; $i++) {
                LinkDefanger::defang($text);
            }

            return (hrtime(true) - $started) / 20;
        };

        $small = max($measure(2_000), 1.0);
        $large = $measure(16_000);

        // Eight times the input. Linear predicts about eight, quadratic about sixty-four; the bound
        // is loose enough that a slow or contended machine cannot fail it by itself.
        $this->assertLessThan(
            24.0,
            $large / $small,
            'defanging is growing faster than the text it runs on'
        );
    }

    /**
     * When PCRE gives up, the value still reaches the reader, with every dot and scheme broken: a
     * live link is the one result that is never acceptable.
     */
    #[Test]
    public function textSurvivesWhenTheEngineGivesUp(): void
    {
        $previous = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '1');

        try {
            $this->assertSame('evil[.]com', LinkDefanger::defang('evil.com'));
            $this->assertSame('https[:]//evil[.]com', LinkDefanger::defang('https://evil.com'));
        } finally {
            ini_set('pcre.backtrack_limit', (string)$previous);
        }
    }

    /**
     * The listed keys are what keeps the email usable. Without this the signup button would point
     * at `https[:]//dev.matecat[.]com`.
     */
    #[Test]
    public function listedKeysAreWrittenVerbatim(): void
    {
        $wrapped = EmailValue::wrapAll(
            ['signup_url' => 'https://dev.matecat.com/invite/abc', 'email' => 'victim@example.com'],
            ['signup_url', 'email']
        );

        $this->assertSame('https://dev.matecat.com/invite/abc', (string)$wrapped['signup_url']);
        $this->assertSame('victim@example.com', (string)$wrapped['email']);
    }

    /**
     * The test that makes the list load-bearing, and the one that fails the day someone adds a link
     * field without listing it. Without this the suite would pass whether or not the list was ever
     * consulted.
     */
    #[Test]
    public function anUnlistedKeyIsDefangedEvenWhenItHoldsALink(): void
    {
        $wrapped = EmailValue::wrapAll(['some_new_link' => 'https://dev.matecat.com/x'], ['signup_url']);

        $this->assertSame('https[:]//dev[.]matecat[.]com/x', (string)$wrapped['some_new_link']);
    }

    /**
     * Exemption is by key name, not by what the value looks like — otherwise attacker-written
     * content could exempt itself by imitating an address. A team called `evil@evil.com` arrives
     * under `name`, and is treated as the free text it is.
     */
    #[Test]
    public function aValueCannotExemptItselfByLookingLikeAnAddress(): void
    {
        $wrapped = EmailValue::wrapAll(['name' => 'evil@evil.com', 'email' => 'evil@evil.com'], ['email']);

        $this->assertSame('evil@evil.com', (string)$wrapped['email'], 'the address field is left alone');
        $this->assertSame('evil@evil.com', (string)$wrapped['name'], 'an address shape is still an address');
    }

    /**
     * The key list has to reach nested values, because `user['email']` and `commenter['email']`
     * arrive inside a `toArray()` nobody restructured for this.
     */
    #[Test]
    public function theKeyListAppliesAtAnyDepth(): void
    {
        $wrapped = EmailValue::wrapAll(
            ['user' => ['email' => 'bob@example.com', 'company' => 'Acme.com']],
            ['email']
        );

        $this->assertSame('bob@example.com', (string)$wrapped['user']['email']);
        $this->assertSame('Acme[.]com', (string)$wrapped['user']['company']);
    }

    /**
     * End to end: the name a reader sees, and the link they need, in one rendered email.
     */
    #[Test]
    public function aRenderedInvitationDefangsTheTeamNameAndKeepsItsLink(): void
    {
        $sender = new UserStruct();
        $sender->uid = 1;
        $sender->email = 'sender@example.com';
        $sender->first_name = 'Ada';
        $sender->last_name = 'Lovelace';

        $team = new TeamStruct();
        $team->id = 1;
        $team->name = 'evil.com';

        $email = new InvitedToTeamEmail($sender, 'invited@example.com', $team);
        $method = new ReflectionMethod(InvitedToTeamEmail::class, '_buildMessageContent');
        $body = (string)$method->invoke($email);

        $this->assertStringContainsString('evil[.]com', $body);
        $this->assertStringNotContainsString('"evil.com"', $body);

        // The half that breaks loudly if the key list is wrong.
        $this->assertMatchesRegularExpression('~href="https://[^"\[]+"~', $body, 'the signup link must stay usable');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function linksABrowserResolves(): array
    {
        return [
            'a dotted IPv4' => ['http://203.0.113.5/login', 'http[:]//203.0.113.5/login'],
            'a decimal IPv4' => ['http://3405803781/login', 'http[:]//3405803781/login'],
            'a hex IPv4' => ['http://0xCB007105/login', 'http[:]//0xCB007105/login'],
            'an IPv6 address' => ['http://[2001:db8::1]/login', 'http[:]//[2001:db8::1]/login'],
            'an IDN suffix' => ["https://matecat-support.\u{0440}\u{0444}/login", "https[:]//matecat-support[.]\u{0440}\u{0444}/login"],
            'a fullwidth dot' => ["https://evil\u{FF0E}com", "https[:]//evil\u{FF0E}com"],
            'an ideographic dot' => ["https://evil\u{3002}com", "https[:]//evil\u{3002}com"],
            'a halfwidth dot' => ["https://evil\u{FF61}com", "https[:]//evil\u{FF61}com"],
            'a percent-encoded dot' => ['https://evil%2Ecom/login', 'https[:]//evil%2Ecom/login'],
            'a zero-width space in the host' => ["https://evil\u{200B}.com/login", "https[:]//evil\u{200B}[.]com/login"],
            'a soft hyphen in the host' => ["https://evil\u{AD}.com/login", "https[:]//evil\u{AD}[.]com/login"],
            'a user part' => ['http://support@evil.com/login', 'http[:]//support@evil[.]com/login'],
            'a user part that names us' => ['https://matecat-support@matecat-login.com/reset', 'https[:]//matecat-support@matecat-login[.]com/reset'],
            'backslashes' => ['http:\\\\evil.com', 'http[:]\\\\evil[.]com'],
        ];
    }

    #[Test]
    #[DataProvider('linksABrowserResolves')]
    public function aSchemeIsBrokenWhateverHostFollowsIt(string $input, string $expected): void
    {
        $this->assertSame($expected, LinkDefanger::defang($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function bareHostnamesInAnyScript(): array
    {
        return [
            'a Cyrillic hostname' => ["\u{043C}\u{0430}\u{0442}\u{0435}\u{043A}\u{0430}\u{0442}.\u{0440}\u{0444}", "\u{043C}\u{0430}\u{0442}\u{0435}\u{043A}\u{0430}\u{0442}[.]\u{0440}\u{0444}"],
            'a zero-width space before the dot' => ["evil\u{200B}.com", "evil\u{200B}[.]com"],
            'a soft hyphen inside a label' => ["ev\u{AD}il.com", "ev\u{AD}il[.]com"],
        ];
    }

    #[Test]
    #[DataProvider('bareHostnamesInAnyScript')]
    public function aBareHostnameIsDefangedInAnyScript(string $input, string $expected): void
    {
        $this->assertSame($expected, LinkDefanger::defang($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function textThatIsNotALink(): array
    {
        return [
            'a Windows path' => ['C:\\\\server\\share'],
            'Japanese prose' => ["\u{6771}\u{4EAC}\u{3002}\u{5927}\u{962A}"],
            'version numbers' => ['1.5 million v5.0.6'],
            'an address in prose' => ['Contact f.surname@example.com today'],
        ];
    }

    #[Test]
    #[DataProvider('textThatIsNotALink')]
    public function textThatIsNotALinkIsKept(string $input): void
    {
        $this->assertSame($input, LinkDefanger::defang($input));
    }

    /**
     * The JIT stack runs out on long input, and the value must still come back defanged rather
     * than as it arrived with the link live.
     */
    #[Test]
    public function aLinkStaysDefangedWhenTheEngineRunsOutOfStack(): void
    {
        $previous = ini_get('pcre.jit');
        ini_set('pcre.jit', '1');

        try {
            $input = "Please re-verify your account at https://matecat-login.com/verify\n" . str_repeat('a.', 7000) . '1';
            $output = LinkDefanger::defang($input);

            $this->assertStringStartsWith("Please re-verify your account at https[:]//matecat-login[.]com/verify\n", $output);
            $this->assertStringNotContainsString('https://matecat-login.com', $output);
        } finally {
            ini_set('pcre.jit', (string)$previous);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function runsOfAddressCharacters(): array
    {
        return [
            'dots' => ['.'],
            'underscores' => ['_'],
            'percent signs and dots' => ['%a.'],
        ];
    }

    /**
     * The same linear bound, for the characters an address may start with. A run of `.`, `_` or
     * `%` started a new address attempt at every character, so its cost grew with the square of its
     * length: 80KB of dots took a second for each email it was in.
     */
    #[Test]
    #[DataProvider('runsOfAddressCharacters')]
    public function defangingCostStaysLinearOnRunsOfAddressCharacters(string $unit): void
    {
        $measure = static function (string $unit, int $length): float {
            $text = str_repeat($unit, intdiv($length, strlen($unit)));
            LinkDefanger::defang($text);                      // warm, so the first call pays no setup

            $started = hrtime(true);
            for ($i = 0; $i < 5; $i++) {
                LinkDefanger::defang($text);
            }

            return (hrtime(true) - $started) / 5;
        };

        $small = max($measure($unit, 2_000), 1.0);
        $large = $measure($unit, 16_000);

        // Eight times the input. Linear predicts about eight, quadratic about sixty-four; the bound
        // is loose enough that a slow or contended machine cannot fail it by itself.
        $this->assertLessThan(
            24.0,
            $large / $small,
            'defanging is growing faster than the text it runs on'
        );
    }

}
