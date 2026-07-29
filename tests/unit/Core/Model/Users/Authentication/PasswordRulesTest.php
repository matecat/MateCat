<?php


namespace Matecat\Core\Model\Users\Authentication;

use Controller\API\Commons\Exceptions\ValidationError;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\Authentication\PasswordRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PasswordRulesTest extends AbstractTest
{
    /**
     * The trait is mixed into controllers, which drag in a request, a response and a database. Hosting
     * it on a bare object keeps these tests on the rules themselves.
     */
    private function rules(): object
    {
        return new class {
            use PasswordRules;
        };
    }

    // ─── rejectControlCharacters ──────────────────────────────────────

    /**
     * @return array<string, array{string}>
     */
    public static function controlCharacterProvider(): array
    {
        return [
            'null byte' => ["Valid!Password\0"],
            'tab' => ["Valid!Pass\tword1"],
            'line feed' => ["Valid!Pass\nword1"],
            'carriage return' => ["Valid!Pass\rword1"],
            'escape' => ["Valid!Pass\x1Bword1"],
            'delete' => ["Valid!Pass\x7Fword1"],
        ];
    }

    #[Test]
    #[DataProvider('controlCharacterProvider')]
    public function rejectControlCharacters_refuses_every_control_character(string $password): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('control characters');

        $this->rules()->rejectControlCharacters($password);
    }

    #[Test]
    public function rejectControlCharacters_allows_printable_and_non_latin_characters(): void
    {
        // Byte-wise matching without the u modifier is what makes this safe: every byte of a multibyte
        // character sits above 0x7F, so no accented or non-Latin password can collide with the range.
        foreach (['Valid!Password1', 'Passwörd!123x', 'пароль!123456', '密碼!123456789', 'pass😀!123456'] as $password) {
            $this->rules()->rejectControlCharacters($password);
        }

        $this->addToAssertionCount(5);
    }

    #[Test]
    public function rejectControlCharacters_ignores_a_value_that_is_not_a_string(): void
    {
        // Request parameters are not guaranteed to be strings: an array arrives when a client sends
        // user[password][]=x. Length and match rules downstream reject it, so this must not blow up.
        $this->rules()->rejectControlCharacters(['array', 'from', 'query']);
        $this->rules()->rejectControlCharacters(null);

        $this->addToAssertionCount(2);
    }

    // ─── validatePasswordRequirements ─────────────────────────────────

    #[Test]
    public function validatePasswordRequirements_accepts_html_special_characters(): void
    {
        // These five are the ones the old escaping rewrote, and the special-character rule below
        // advertises them. They must be usable.
        $this->rules()->validatePasswordRequirements('Valid&Pass<word>1', 'Valid&Pass<word>1');
        $this->rules()->validatePasswordRequirements('Quote"And\'Apos1', 'Quote"And\'Apos1');

        $this->addToAssertionCount(2);
    }

    #[Test]
    public function validatePasswordRequirements_counts_length_in_characters_not_bytes(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('at least 12 characters');

        // Seven characters, nineteen bytes.
        $this->rules()->validatePasswordRequirements('密碼密!碼碼碼', '密碼密!碼碼碼');
    }

    #[Test]
    public function validatePasswordRequirements_accepts_twelve_non_latin_characters(): void
    {
        $password = '密碼密碼密碼密碼密碼密!';

        $this->rules()->validatePasswordRequirements($password, $password);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function validatePasswordRequirements_rejects_more_than_fifty_characters(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('maximum of 50 characters');

        $password = str_repeat('a', 50) . '!X';
        $this->rules()->validatePasswordRequirements($password, $password);
    }

    #[Test]
    public function validatePasswordRequirements_rejects_a_mismatched_confirmation(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Passwords must match');

        $this->rules()->validatePasswordRequirements('Valid!Password1', 'Valid!Password2');
    }

    #[Test]
    public function validatePasswordRequirements_requires_a_special_character(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('special character');

        $this->rules()->validatePasswordRequirements('NoSpecialsHere1', 'NoSpecialsHere1');
    }
}
