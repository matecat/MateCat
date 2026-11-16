<?php

declare(strict_types=1);

namespace Model\FeaturesBase\Hook\Event\Filter;

use Model\FeaturesBase\Hook\FilterEvent;
use Utils\Logger\LoggerFactory;

/**
 * Collects the hosts the loaded plugins add to the page's Content-Security-Policy, by directive.
 *
 * Example: `$event->addSources('default-src', 'a0.muscache.com');`
 *
 * The template reads only the directives it declares (`${x_plugin_csp/default-src | nothing}`), so any other
 * key is ignored and a plugin can never introduce a directive. That matters because a directive the template
 * does not declare falls back to default-src: declaring it with a plugin's hosts alone would drop every
 * source default-src grants.
 *
 * Sources are string literals written in our own plugins; they never come from users, projects or the
 * database. The only check is the one mistake that would break the policy: a source must be a single token.
 *
 * @see \Controller\Abstracts\BaseKleinViewController::render()
 * @see lib/View/templates/_APIDoc.php
 */
final class ContentSecurityPolicySourcesEvent extends FilterEvent
{
    /** @var array<string, string> directive => space-separated sources */
    private array $sources = [];

    public static function hookName(): string
    {
        return 'contentSecurityPolicySources';
    }

    public function addSources(string $directive, string ...$sources): void
    {
        foreach ($sources as $source) {
            // One token only: a space, ';', ',' or a quote would end the directive or add a keyword, and '&',
            // '<' or '>' could spell one of those as an entity on a page that does not escape (_APIDoc.php).
            // A bad source is skipped, not thrown: an exception would stop this plugin's hook, so every source
            // it adds after this one would be lost.
            if (preg_match('/^[^\s;,\'"&<>]+$/D', $source) !== 1) {
                LoggerFactory::getLogger('feature_set')->warning("Skipped Content-Security-Policy source for $directive: \"$source\"");
                continue;
            }

            $this->sources[$directive] = ltrim(($this->sources[$directive] ?? '') . ' ' . $source);
        }
    }

    /**
     * @return array<string, string>
     */
    public function getSources(): array
    {
        return $this->sources;
    }
}
