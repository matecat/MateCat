<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use InvalidArgumentException;
use Model\FeaturesBase\Hook\FilterEvent;
use Utils\Templating\CspDirective;

/**
 * Collects the hosts the loaded plugins need in the page's Content-Security-Policy, such as the CDN a
 * customer serves its context screenshots from.
 *
 * Each plugin owns the hosts it adds: the core only checks that every token is a well-formed source, so that
 * a value cannot close the directive and open another one. The policy can only be widened here. There is no
 * way to remove or replace a source the template grants.
 *
 * The event is dispatched when the page is rendered, not when the view is set up, because views such as the
 * cattool and the context preview load the project's features after `setView()`.
 *
 * @see \Controller\Abstracts\BaseKleinViewController::applyPluginCspSources() — dispatch site
 */
final class ContentSecurityPolicySourcesEvent extends FilterEvent
{
    /**
     * A host source (`host`, `*.host`, `scheme://host`, `host:port`, `host/path`) or a bare scheme (`https:`).
     * Whitespace, `;`, `,` and quotes are all outside the character classes, which is what keeps a source from
     * ending its directive or smuggling in a keyword such as `'unsafe-inline'`.
     */
    private const string SOURCE_PATTERN = '#^(?:[a-z][a-z0-9+.\-]*:(?://)?)?(?:\*|(?:\*\.)?[a-z0-9\-]+(?:\.[a-z0-9\-]+)*)(?::(?:\d{1,5}|\*))?(?:/[a-z0-9\-._~%/]*)?$|^[a-z][a-z0-9+.\-]*:$#i';

    /** @var array<string, list<string>> */
    private array $sources = [];

    public static function hookName(): string
    {
        return 'contentSecurityPolicySources';
    }

    public function __construct(
        private readonly string $templateName,
    ) {
    }

    /**
     * The template being rendered (`context_preview.html`), for a plugin that needs its hosts on one page only.
     */
    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    /**
     * @throws InvalidArgumentException when a source is not a well-formed CSP source expression
     */
    public function addSources(CspDirective $directive, string ...$sources): void
    {
        foreach ($sources as $source) {
            if (preg_match(self::SOURCE_PATTERN, $source) !== 1) {
                throw new InvalidArgumentException("Invalid Content-Security-Policy source for $directive->value: \"$source\"");
            }

            $current = $this->sources[$directive->value] ?? [];
            if (!in_array($source, $current, true)) {
                $current[] = $source;
            }
            $this->sources[$directive->value] = $current;
        }
    }

    /**
     * @return list<string>
     */
    public function getSources(CspDirective $directive): array
    {
        return $this->sources[$directive->value] ?? [];
    }
}
