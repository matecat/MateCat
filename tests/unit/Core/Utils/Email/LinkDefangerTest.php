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
            'a bare hostname'          => ['evil.com', 'evil[.]com'],
            'every label, not just one' => ['login.microsoftonline.com', 'login[.]microsoftonline[.]com'],
            'a hostname inside prose'  => ['Verify at evil-login.co now', 'Verify at evil-login[.]co now'],

            // Entity forms. Emails escape with double_encode: false, so entity text reaches the
            // recipient and their client turns it back into a dot — matching only the literal
            // character would let these through as live hostnames.
            'a decimal entity dot'     => ['evil&#46;com', 'evil[.]com'],
            'a hex entity dot'         => ['evil&#x2E;com', 'evil[.]com'],
            'a named entity dot'       => ['evil&period;com', 'evil[.]com'],

            // Schemes: the colon rather than the scheme's letters, so one rule covers all of them.
            'an http scheme'           => ['https://evil.com', 'https[:]//evil[.]com'],
            'a path is kept'           => ['https://evil.com/login?a=1', 'https[:]//evil[.]com/login?a=1'],
            'a javascript scheme'      => ['javascript://evil.com', 'javascript[:]//evil[.]com'],

            // Addresses are recognised whole. Half-defanging one is worse than either choice made
            // consistently, and roughly twenty real teams are named after a member's address.
            'an address is untouched'  => ['f.surname@example.com', 'f.surname@example.com'],
            'a long address'           => ['student.if17@campus.edu.ua', 'student.if17@campus.edu.ua'],

            // The cost, asserted so it is visible here rather than discovered by a customer.
            'a real customer name'     => ['Acme.com', 'Acme[.]com'],
            'a real customer in prose' => ['Acme.guru Polite statements', 'Acme[.]guru Polite statements'],

            // False positives Apple Mail would not have linkified. No shape-based rule can tell
            // these from a real company name ending in a live suffix, which it did.
            'a non-TLD suffix'         => ['Alpha.Beta', 'Alpha[.]Beta'],
            'an abbreviation pair'     => ['Ejercicio HH.CC Bilbo y Frodo', 'Ejercicio HH[.]CC Bilbo y Frodo'],

            // Left alone: a single trailing letter is not a suffix.
            'an abbreviation'          => ['Translated S.r.l.', 'Translated S.r.l.'],
            'ordinary text'            => ['Marketing Team', 'Marketing Team'],
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
     * PCRE gives up on pathological input by returning null, and a value that fails to defang must
     * still reach the reader. Emitting an empty string instead would delete it from the email.
     */
    #[Test]
    public function textSurvivesWhenTheEngineGivesUp(): void
    {
        $previous = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '1');

        try {
            $this->assertSame('evil.com', LinkDefanger::defang('evil.com'));
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
        $sender             = new UserStruct();
        $sender->uid        = 1;
        $sender->email      = 'sender@example.com';
        $sender->first_name = 'Ada';
        $sender->last_name  = 'Lovelace';

        $team       = new TeamStruct();
        $team->id   = 1;
        $team->name = 'evil.com';

        $email  = new InvitedToTeamEmail($sender, 'invited@example.com', $team);
        $method = new ReflectionMethod(InvitedToTeamEmail::class, '_buildMessageContent');
        $body   = (string)$method->invoke($email);

        $this->assertStringContainsString('evil[.]com', $body);
        $this->assertStringNotContainsString('"evil.com"', $body);

        // The half that breaks loudly if the key list is wrong.
        $this->assertMatchesRegularExpression('~href="https://[^"\[]+"~', $body, 'the signup link must stay usable');
    }

}
