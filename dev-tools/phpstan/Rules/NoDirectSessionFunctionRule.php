<?php

declare(strict_types=1);

namespace Matecat\PhpStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Forbids calling any `session_*` function, or setting any `session.*` ini key, outside an explicit
 * allowlist.
 *
 * The companion to NoDirectSessionSuperglobalRule: that rule governs how session state is read, this
 * one governs the session's existence — who may configure it, start it, rotate its id or end it.
 * Both exist for the same reason as NoDirectBootstrapGetDatabaseRule, that an architectural boundary
 * which is only a convention gets crossed, and the crossing is invisible until someone goes looking.
 *
 * Calling the functions directly is not equivalent to going through the adapter. `PhpSession::start()`
 * refuses when the runtime reports PHP_SESSION_DISABLED, a misconfiguration a page cannot recover
 * from; bare `session_start()` only warns and returns false there, so the page carries on with no
 * session and no indication of why. It is also idempotent, so a direct call ahead of it adds nothing
 * — it only takes the decision away from the one place allowed to make it.
 *
 * Unlike the superglobal allowlist, this one is not expected to shrink: it names the adapter and
 * nothing else, which is already the end state.
 *
 * @implements Rule<FuncCall>
 */
class NoDirectSessionFunctionRule implements Rule
{
    /**
     * The ini accessors that can change a session setting. `ini_get` is deliberately absent: reading
     * a setting crosses no boundary, and a rule that flagged it would push pointless indirection onto
     * diagnostics and tests.
     */
    private const array INI_WRITERS = ['ini_set', 'ini_alter'];

    /**
     * @param list<string> $allowedFiles repository-relative paths permitted to touch the session runtime
     */
    public function __construct(private array $allowedFiles = [])
    {
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof FuncCall) {
            return [];
        }

        // A callee can be an expression ($fn(), $obj->fn()), which names no function statically. A
        // dynamic call is out of reach of a static rule either way, so only a literal name is judged.
        if (!$node->name instanceof Name) {
            return [];
        }

        // Namespaced code resolves an unqualified call to the global function, and either spelling
        // reaches the same one, so compare on the last part rather than the written form. Function
        // names are case-insensitive in PHP, so `Session_Start()` is the same call.
        $function = strtolower($node->name->getLast());

        $message = $this->violation($function, $node);

        if ($message === null || $this->isAllowed($this->declaringFile($scope))) {
            return [];
        }

        return [
            RuleErrorBuilder::message($message)
                ->identifier('matecat.directSessionFunction')
                ->build(),
        ];
    }

    /**
     * The message for this call, or null when the call is none of this rule's business.
     */
    private function violation(string $function, FuncCall $node): ?string
    {
        if (str_starts_with($function, 'session_')) {
            return sprintf(
                'Do not call %s() directly. Reach the session runtime through Utils\Session\PhpSession, '
                . 'which is the one place that decides a session exists and refuses when sessions are '
                . 'disabled instead of continuing without one.',
                $function
            );
        }

        if (!in_array($function, self::INI_WRITERS, true)) {
            return null;
        }

        $setting = $this->literalFirstArgument($node);

        // A computed setting name cannot be judged statically. Ignoring it keeps the rule honest
        // about what it checks rather than guessing.
        if ($setting === null || !str_starts_with(strtolower($setting), 'session.')) {
            return null;
        }

        return sprintf(
            'Do not set the "%s" ini setting here. The session cookie and storage are configured in '
            . 'Utils\Session\PhpSession, so the settings that shape a session live beside the code '
            . 'that starts one.',
            $setting
        );
    }

    /**
     * The first argument when it is written as a plain string, which is the only form whose value is
     * known without evaluating anything.
     */
    private function literalFirstArgument(FuncCall $node): ?string
    {
        $first = $node->getArgs()[0] ?? null;

        if ($first === null || !$first->value instanceof String_) {
            return null;
        }

        return $first->value->value;
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
