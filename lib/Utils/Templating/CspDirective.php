<?php

declare(strict_types=1);

namespace Utils\Templating;

/**
 * The Content-Security-Policy directives a plugin may add sources to.
 *
 * Only directives that `CSP-HeaderMeta.html` already declares are listed. A directive the template does not
 * declare falls back to `default-src`: introducing it with a plugin's hosts alone would drop every source
 * `default-src` grants, so a plugin asking for an image host would cut the page off from every other image.
 * A new case therefore needs its placeholder in each copy of the template (core and `internal_scripts`), and in
 * the two API doc pages that substitute the placeholders by hand.
 */
enum CspDirective: string
{
    case DEFAULT_SRC = 'default-src';
    case SCRIPT_SRC_ELEM = 'script-src-elem';
    case CHILD_SRC = 'child-src';
    case FONT_SRC = 'font-src';
    case STYLE_SRC = 'style-src';

    /**
     * The view variable the template reads this directive's plugin sources from: `x_plugin_csp_default_src`.
     */
    public function templateVariable(): string
    {
        return 'x_plugin_csp_' . str_replace('-', '_', $this->value);
    }
}
