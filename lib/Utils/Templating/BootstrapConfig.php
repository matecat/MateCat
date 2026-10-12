<?php

namespace Utils\Templating;

use JsonSerializable;
use PHPTAL;
use RuntimeException;
use Stringable;

/**
 * The page configuration, rendered as a single JSON document.
 *
 * PHPTAL performs no HTML escaping inside a script block — it rewrites `</` and nothing else — so a
 * value interpolated between quotes there ends the JavaScript string literal as soon as it contains
 * one. Rather than deciding how to escape each value at each interpolation, the template interpolates
 * one whole JSON document — `var config = ${structure config_json};` — which supplies its own quoting
 * for every string it contains, so no template ever puts a quote around a value again.
 *
 * The values are not stored here. This object holds the view and reads its variables back when it is
 * rendered, so there is exactly one copy of every value — PHPTAL's own — and exactly one encode, at
 * the moment the template asks for the document. That timing is what makes it complete: everything
 * assigned to the view after `setView()` returns, including anything a view decorator adds, is
 * already in place by then.
 *
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 */
class BootstrapConfig implements Stringable
{
    /**
     * PHPTAL's own context properties, which are not template variables. Variables whose name begins
     * with an underscore cannot exist — `PHPTAL_Context::__set()` rejects them — so `repeat` is the
     * only one of these a template could collide with, and it is reserved by `tal:repeat`.
     */
    private const CONTEXT_PROPERTIES = ['repeat', '_xmlDeclaration', '_docType'];

    /**
     * Variables that are deliberately kept out of the document.
     *
     * The nonce is the load-bearing one: a nonce the page can read back is a nonce an injected script
     * can read back, which is what it exists to prevent. Excluding the variable that holds it is not
     * enough on its own — `vite_html` is markup carrying `nonce="…"` on every tag the dev server
     * injects, so it would hand the same value back under a different name. The remaining entries are
     * lists of markup fragments a decorator appends to and the layout repeats over — template
     * plumbing, not configuration, and none of them has a reader in the page's JavaScript.
     *
     * `flashMessages` is excluded for the same reason: the footer macro already emits it as
     * `config.flash_messages` (common.html), which is the name `commonUtils.js:570` reads. Carrying it
     * here too would put the same payload on the page twice under two names, one of which nothing
     * reads — and would put it on the three pages that render no footer macro at all.
     */
    private const array EXCLUDED = ['x_nonce_unique_id', 'vite_html', 'footer_js', 'config_js', 'flashMessages'];

    private const int JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

    /**
     * @param PHPTAL $view the view whose variables are the configuration
     */
    public function __construct(private readonly PHPTAL $view)
    {
    }

    /**
     * @return string
     *
     * @throws RuntimeException when a value cannot be encoded, malformed UTF-8 being the realistic
     *                          cause. Preferred to emitting an empty document, which would leave the
     *                          page silently unconfigured.
     */
    public function __toString(): string
    {
        // Cast at the top level only: an empty PHP array encodes as `[]`, which would hand the page a
        // JavaScript array where it expects an object. Nested arrays stay arrays, as they must.
        $encoded = json_encode((object)$this->collect(), self::JSON_FLAGS);

        if ($encoded === false) {
            throw new RuntimeException('Unable to encode the page configuration: ' . json_last_error_msg());
        }

        return $encoded;
    }

    /**
     * The view's variables, minus the ones that must not travel to the page.
     *
     * @return array<string, mixed>
     */
    private function collect(): array
    {
        $collected = [];

        foreach (get_object_vars($this->view->getContext()) as $name => $value) {
            if (in_array($name, self::CONTEXT_PROPERTIES, true) || in_array($name, self::EXCLUDED, true)) {
                continue;
            }

            if (!self::isSerializable($value)) {
                continue;
            }

            $collected[$name] = $value;
        }

        return $collected;
    }

    /**
     * Whether a value may go into the document.
     *
     * Domain objects are kept out: `json_encode()` emits every public property of an object that does
     * not declare its own serialised form, so a struct assigned to the view would publish columns the
     * page never asked for. Only scalars, null and the templating wrappers that implement
     * JsonSerializable are allowed through, and an array only when every element is. This object
     * fails the test itself, which is what keeps the document from containing itself.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private static function isSerializable(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $element) {
                if (!self::isSerializable($element)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || is_scalar($value) || $value instanceof JsonSerializable;
    }
}
