<?php

declare(strict_types=1);

namespace Utils\Session;

use Error;

/**
 * Raised when a controller declared stateless touches session state.
 *
 * Deliberately an `Error` and not an `Exception`, which is the difference between this boundary
 * being enforced and merely being decorative. Controllers here routinely wrap an action body in
 * `catch (Exception $e)` and render the message as an ordinary error response; `V2\UserController::edit()`
 * has exactly that shape (it is itself stateful, so it never raises this — it is cited for the
 * handler, not as an offender). A `LogicException` thrown from this store would be caught by any
 * handler of that shape and reported as a routine validation-style failure, so a stateless endpoint
 * reaching for the session would be indistinguishable from a bad request instead of surfacing as the
 * programming error it is. `Error` passes straight through `catch (Exception)`.
 *
 * That is also the honest classification: a stateless controller reading session state is a mistake
 * in the code, not a runtime condition the request could have avoided. `Error` is unchecked, so it
 * needs no `@throws` plumbing through every caller either.
 */
class StatelessSessionViolation extends Error
{
}
