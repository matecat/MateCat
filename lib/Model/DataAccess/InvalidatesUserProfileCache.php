<?php
/**
 * @package Model\DataAccess
 * @author  Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * @date    31/07/26
 */

namespace Model\DataAccess;

use Controller\Abstracts\Authentication\UserStateStore;
use Exception;
use ReflectionException;

/**
 * Drops a user's cached profile from the write methods of the DAOs the profile is built from.
 *
 * Used by the four DAOs whose rows the payload reads — UserDao, MetadataDao, MembershipDao and
 * ConnectedServiceDao — rather than living on AbstractDao: a DAO that has nothing to do with user
 * state should not carry the dependency, and a DAO that uses this trait advertises that it does.
 *
 * The invalidation hangs off the *write* methods, not off the cache-bust methods
 * ({@see \Model\Users\UserDao::destroyCache()}). A write always runs; a bust runs only where a
 * caller adds one, and only when that DAO instance has a TTL set, since cacheTTL defaults to 0.
 * Hooking writes is what makes "a change to user data is always reflected" a property of the code
 * instead of a convention.
 */
trait InvalidatesUserProfileCache
{

    private ?UserStateStore $userStateStore = null;

    /**
     * Substitute the store, for tests and for any caller that already holds one.
     *
     * The dependency is a settable collaborator rather than a static call so that a DAO write can be
     * exercised without a Redis connection: the store reaches Redis through DaoCacheTrait, whose
     * connection is opened on demand, so a hard-wired call would silently give every DAO write test
     * a live connection to the application Redis database.
     */
    public function setUserStateStore(UserStateStore $store): static
    {
        $this->userStateStore = $store;

        return $this;
    }

    /**
     * The uid is required rather than nullable: every write boundary either takes an `int` uid or has
     * just read the row back from the database, so there is no reachable call with no uid. A nullable
     * parameter would only absorb the declared type of UserStruct::getUid(), at the price of making a
     * missed invalidation look like a tolerated case.
     *
     * @throws ReflectionException
     * @throws Exception
     */
    protected function invalidateUserProfileCache(int $uid): void
    {
        ($this->userStateStore ??= new UserStateStore())->invalidateProfile($uid);
    }

}
