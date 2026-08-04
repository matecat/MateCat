<?php

declare(strict_types=1);

namespace Matecat\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Forbids naming the `$_SESSION` superglobal outside an explicit allowlist.
 *
 * Modelled on NoDirectBootstrapGetDatabaseRule, and for the same reason: an architectural boundary
 * that is only a convention gets crossed, and the crossing is invisible until someone goes looking.
 * Here the boundary is that session storage is reached through the `SessionStore` interface, so a key
 * group can change backing store without editing every consumer.
 *
 * The allowlist is expected to shrink. It starts naming every file that reads the superglobal today
 * and loses an entry as each one converts; the end state names only `PhpSessionStore`, at which point
 * the superglobal has exactly one reader and one writer in the tree. Keeping the list explicit means
 * the remaining work is visible in configuration rather than needing a grep to rediscover.
 *
 * @implements Rule<Variable>
 */
class NoDirectSessionSuperglobalRule implements Rule
{
    /**
     * @param list<string> $allowedFiles repository-relative paths permitted to name $_SESSION
     */
    public function __construct(private array $allowedFiles = [])
    {
    }

    public function getNodeType(): string
    {
        return Variable::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Variable) {
            return [];
        }

        // A variable name can itself be an expression ($$name), which is never the superglobal.
        if (!is_string($node->name) || $node->name !== '_SESSION') {
            return [];
        }

        if ($this->isAllowed($this->declaringFile($scope))) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Do not use the $_SESSION superglobal directly. Inject Utils\Session\SessionStore and '
                . 'read through it, so session storage can change without editing this call site.'
            )
                ->identifier('matecat.directSessionSuperglobal')
                ->build(),
        ];
    }

    /**
     * The file the offending line physically lives in.
     *
     * Trait bodies are analysed once per using class, and for that analysis `Scope::getFile()` returns
     * the *using class's* file, not the trait's — even though the error is reported at the trait's
     * path and line. Resolving against the scope file alone is wrong in both directions: an
     * allowlisted trait is reported anyway (once per user), and a violation inside a trait is excused
     * whenever some using class happens to be allowlisted. The allowlist names the file the source
     * line is written in, so the trait's own file is the one that must govern.
     */
    private function declaringFile(Scope $scope): string
    {
        $traitFile = $scope->getTraitReflection()?->getFileName();

        return $traitFile ?? $scope->getFile();
    }

    /**
     * Suffix match on a normalised path: PHPStan reports absolute paths, and the allowlist is written
     * repository-relative so it stays readable and machine-independent.
     */
    private function isAllowed(string $analysedFile): bool
    {
        $analysedFile = str_replace('\\', '/', $analysedFile);

        foreach ($this->allowedFiles as $allowedFile) {
            $allowedFile = str_replace('\\', '/', $allowedFile);

            if ($analysedFile === $allowedFile || str_ends_with($analysedFile, '/' . $allowedFile)) {
                return true;
            }
        }

        return false;
    }
}
