<?php

namespace Model\Users;

/**
 * Which flow an auth token was minted for.
 *
 * A single column serves both the signup confirmation and the password reset, and the lookup is a
 * plain equality match, so nothing about a stored token said which flow had produced it. Either flow
 * therefore accepted the other's token, and each applied its own lifetime — a "confirm your account"
 * link, which users treat as harmless and which lives for days, was consequently usable as a "set any
 * password" link for as long as the reset window allowed.
 *
 * The marker below is stored as part of the token, and each flow prepends its own before looking one
 * up, so a token presented to the wrong flow matches no row at all.
 *
 * Modelled as an enum rather than a pair of string constants so that the lifetime is always derived
 * from the scope: a new flow cannot be introduced without giving it one, and no caller is in a
 * position to pass a window that disagrees with the one validation will use.
 */
enum AuthTokenScope: string
{

    case PasswordReset = 'r_';
    case SignupConfirmation = 'c_';

    /**
     * The marker prepended to the stored token.
     *
     * Two characters. The column is varchar(255) and a stored token is this marker plus a
     * 48-character secret, so a longer marker no longer has to be paid for with a shorter secret.
     */
    public function marker(): string
    {
        return $this->value;
    }

    /**
     * How long a token of this scope stays usable.
     */
    public function ttlSeconds(): int
    {
        return match ($this) {
            self::PasswordReset => 1800,
            self::SignupConfirmation => 259200,
        };
    }

}
