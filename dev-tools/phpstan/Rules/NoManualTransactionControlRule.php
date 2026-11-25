<?php

declare(strict_types=1);

namespace Matecat\PhpStan\Rules;

use Model\DataAccess\IDatabase;
use PDO;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ObjectType;

/**
 * Forbids hand-rolled transaction windows: PDO::beginTransaction()/commit()/rollBack() and
 * IDatabase::begin()/commit()/rollback() outside the connection object that implements them.
 *
 * A transaction is a scope, not three statements. IDatabase::transaction(callable) opens the
 * window, commits it on a normal return and rolls it back on any Throwable before re-throwing,
 * so no failure path can be left without a rollback and no early return can leave the window
 * open on a connection the request or the worker hands back.
 *
 * The statements also carry work the connection object owns. Database::commit() drains the
 * deferred cache evictions that DaoCacheTrait queued while the transaction was open; a raw
 * PDO commit leaves that queue undrained and the next begin() discards it, so the rows stay
 * readable from cache with the pre-commit values for the whole TTL.
 *
 * inTransaction() is not covered: reading the state is how a nested scope decides it is a guest.
 *
 * The rule matches on the receiver's type, which is what makes an indirect receiver such as
 * $this->teamDao->getDatabaseHandler()->begin() fall out for free. What it cannot see is a facade:
 * a class or a trait that wraps the statements behind methods of its own, because at the call site
 * the receiver is a domain object. Two of those were found and deleted rather than allowlisted
 * (TransactionalTrait, and ProjectCompletionRepository's three forwarders). Do not add a third —
 * this rule will not catch it.
 *
 * The allowlist is a hole by construction. Database::nextSequence() used to sit inside the
 * allowlisted file and open a raw transaction that neither this rule nor any depth counter could
 * see; it now refuses to run inside one instead. Keep the list at the implementation itself.
 *
 * @implements Rule<MethodCall>
 */
final class NoManualTransactionControlRule implements Rule
{
    /** @var array<string, string> lower-case method name => name as written, for PDO receivers */
    private const PDO_METHODS = [
        'begintransaction' => 'beginTransaction',
        'commit' => 'commit',
        'rollback' => 'rollBack',
    ];

    /** @var array<string, string> lower-case method name => name as written, for IDatabase receivers */
    private const DATABASE_METHODS = [
        'begin' => 'begin',
        'commit' => 'commit',
        'rollback' => 'rollback',
    ];

    /** @var list<string> normalized ("/"-separated, leading-slash) path suffixes allowed to call them */
    private array $allowedFileSuffixes;

    /**
     * @param list<string> $allowedFiles project-relative paths permitted to issue the statements
     */
    public function __construct(array $allowedFiles)
    {
        $this->allowedFileSuffixes = array_map(
            static fn(string $p): string => '/' . ltrim(str_replace('\\', '/', $p), '/'),
            $allowedFiles
        );
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @return list<RuleError>
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall) {
            return [];
        }

        // Dynamic method names carry no name to match against.
        if (!$node->name instanceof Identifier) {
            return [];
        }

        $called = strtolower($node->name->name);
        if (!isset(self::PDO_METHODS[$called]) && !isset(self::DATABASE_METHODS[$called])) {
            return [];
        }

        $file = str_replace('\\', '/', $scope->getFile());
        foreach ($this->allowedFileSuffixes as $suffix) {
            if (str_ends_with($file, $suffix)) {
                return [];
            }
        }

        $receiver = $scope->getType($node->var);

        // commit() and rollback() are named on both receivers, so the receiver type decides which
        // message is right. A type that is only maybe one of the two produces neither: the rule
        // reports what it can prove.
        if (
            isset(self::PDO_METHODS[$called])
            && (new ObjectType(PDO::class))->isSuperTypeOf($receiver)->yes()
        ) {
            return [
                RuleErrorBuilder::message(
                    'Raw PDO::' . self::PDO_METHODS[$called] . '() is not allowed. Wrap the work in '
                    . 'IDatabase::transaction(callable) instead: it owns the boundary, rolls back on any '
                    . 'Throwable before re-throwing, and drains the cache evictions the transaction '
                    . 'deferred — a raw commit leaves that queue undrained and the next begin() '
                    . 'discards it.'
                )
                    ->identifier('matecat.manualTransactionControl')
                    ->build(),
            ];
        }

        if (
            isset(self::DATABASE_METHODS[$called])
            && (new ObjectType(IDatabase::class))->isSuperTypeOf($receiver)->yes()
        ) {
            return [
                RuleErrorBuilder::message(
                    'IDatabase::' . self::DATABASE_METHODS[$called] . '() is not allowed here. A transaction '
                    . 'is a scope, not three statements: call IDatabase::transaction(callable), which commits '
                    . 'on a normal return and rolls back on any Throwable, so no failure path and no early '
                    . 'return can leave the window open.'
                )
                    ->identifier('matecat.manualTransactionControl')
                    ->build(),
            ];
        }

        return [];
    }
}
