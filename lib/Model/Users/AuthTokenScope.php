<?php

namespace Model\Users;

use Utils\Registry\AppConfig;

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
     * The value stored in `users.confirmation_token` for a raw token of this scope.
     *
     * The marker stays in clear: it is a flow label rather than a secret, and the scope checks read
     * it back off the stored value. The random part is replaced by an HMAC keyed on the instance
     * auth secret, so a copy of the table is not a set of spendable links. Someone holding the
     * stored value cannot derive a raw token that hashes to it, and cannot present the stored value
     * itself either, because whatever arrives is hashed again before the lookup.
     *
     * Keyed rather than a bare digest so that reading the database is not enough on its own: the
     * secret lives in the filesystem, not in the table.
     */
    public function storedForm(string $rawToken): string
    {
        return $this->marker() . hash_hmac('sha256', $rawToken, AppConfig::$AUTHSECRET);
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
