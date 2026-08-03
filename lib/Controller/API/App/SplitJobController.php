<?php

namespace Controller\API\App;

use Utils\Session\SessionStore;

/**
 * The UI's split and merge endpoints: /api/app/split-job-{apply,check,merge}.
 *
 * Identical behaviour to the v2/v3 controller it extends, with one difference: it is stateful, so the
 * cached outsource quote that JobSplitMergeService invalidates on split and merge is actually reachable.
 *
 * The split exists because that cart lives in the session, and only the UI has one. The 2015 predecessor
 * of the shared controller declared its session explicitly (`//SESSION ENABLED` plus a sessionStart() in
 * its constructor); the migration to the stateless KleinController dropped it, and the invalidation has
 * been writing to a throwaway array ever since. Declaring the shared class stateful would have restored
 * it at the cost of opening a session on every api-key call, so the statefulness lives here instead —
 * the same App-stateful / V3-stateless pairing already used by TmKeyManagementController.
 *
 * Subclassing rather than duplicating: unlike TmKeyManagementController, where the App and V3 variants
 * expose genuinely different methods, these two differ only in whether a session exists. Nothing is
 * overridden but the session flag and the cart store.
 *
 * @see \Controller\API\V2\SplitJobController
 */
class SplitJobController extends \Controller\API\V2\SplitJobController
{

    /**
     * The one reason this class exists. Re-declared here rather than by extending
     * AbstractStatefulKleinController, because the behaviour to inherit lives in the v2 controller and
     * PHP has no multiple inheritance. Statefulness stays a static property of the class, so the
     * boundary remains decidable from the hierarchy alone.
     */
    protected bool $useSession = true;

    protected function outsourceCartStore(): ?SessionStore
    {
        return $this->sessionStore();
    }

}
