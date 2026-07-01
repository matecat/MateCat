#!/bin/bash

#
# check-sync.sh — Verify submodule branches are aligned with their remotes.
#
# Iterates over all git submodules and checks whether the local branch matches
# the remote (origin) branch. Reports synced, ahead/behind, or errors (missing
# branch, missing remote, etc.). Can be run from anywhere inside the repository;
# it relocates to the repository root automatically.
#
# Usage:
#   bash check-sync.sh [options] [branch]
#
# Options:
#   --push     Push local-ahead commits to the remote for each submodule that is
#              currently on the target branch. Does NOT pull.
#   --update   Bring each submodule's local branch up to date with origin by
#              rebasing it onto origin/<branch>, WITHOUT moving the working
#              checkout: HEAD is restored to its original position afterward, so
#              the superproject gitlink stays unchanged. Use when submodules are
#              parked at the recorded commit but the branch ref lags the remote.
#   --sync     Like --update, but leaves the submodule checked out ON the updated
#              branch tip (newer code). The superproject will then see the gitlink
#              as moved. Use when `git submodule` shows detached HEADs and you want
#              the submodules back on their branch, up to date.
#   --dry-run  Show what would happen without any network writes or mutations.
#              Skips fetch and reports on cached remote-tracking state; combined
#              with an action flag, prints the action it would perform.
#
# --push, --update and --sync are mutually exclusive.
#
# Arguments:
#   branch   Optional. The branch name to check (e.g., context-review, develop).
#            If omitted, auto-detects main or master per submodule.
#
# Examples:
#   bash check-sync.sh                       # check main/master alignment
#   bash check-sync.sh context-review        # check context-review alignment
#   bash check-sync.sh --push                # push local-ahead commits on main/master
#   bash check-sync.sh --update              # rebase local branches to origin, keep checkout
#   bash check-sync.sh --sync                # rebase and move checkout onto the branch tip
#   bash check-sync.sh --update --dry-run    # show what --update would rebase, no changes
#
# Output:
#   ✅ plugin (branch) — synced (sha)
#   ❌ plugin (branch) — local: sha remote: sha [N unpushed, N behind remote]
#   ⚠️  plugin — error description
#

MODE=""            # "" | push | update | sync (mutually exclusive)
DRY_RUN=false
BRANCH=""

# Set MODE, rejecting a second action flag.
set_mode() {
    if [ -n "$MODE" ] && [ "$MODE" != "$1" ]; then
        echo "⚠️  --push, --update and --sync are mutually exclusive (got --$MODE and --$1)" >&2
        exit 1
    fi
    MODE="$1"
}

for arg in "$@"; do
    case "$arg" in
        --push)    set_mode push ;;
        --update)  set_mode update ;;
        --sync)    set_mode sync ;;
        --dry-run) DRY_RUN=true ;;
        -*)        echo "⚠️  Unknown flag: $arg" >&2; exit 1 ;;
        *)
            if [ -n "$BRANCH" ]; then
                echo "⚠️  Multiple branch arguments given ('$BRANCH', '$arg'); only one is allowed" >&2
                exit 1
            fi
            BRANCH="$arg"
            ;;
    esac
done

# Must run inside a git repository; relocate to its root so submodule paths
# (e.g. plugins/aligner) resolve consistently regardless of the invocation dir.
REPO_ROOT=$(git rev-parse --show-toplevel 2>/dev/null) || {
    echo "⚠️  not inside a git repository" >&2
    exit 1
}
cd "$REPO_ROOT" || exit 1

# Collect registered submodules via git submodule status.
SUBMODULES=()
while IFS= read -r line; do
    # Status prefix char can be ' ', '+', '-', or 'U' followed by a 40-char sha.
    # Note: submodule paths containing spaces are not supported (capture stops at
    # the first whitespace) — acceptable for this repo.
    if [[ $line =~ ^[[:space:]]*[-+Ua-f0-9]+[[:space:]]+([^[:space:]]+) ]]; then
        SUBMODULES+=("${BASH_REMATCH[1]}")
    fi
done < <(git submodule status)

# Also collect root-level directories that are git repos but are not registered
# submodules (defensive; usually a no-op). A submodule's .git is a FILE, a plain
# repo's is a DIR — test -e to cover both.
ALL_SUBMODULES=("${SUBMODULES[@]}")
for dir in ./*/; do
    [ -e "$dir/.git" ] || continue
    DIR_NAME=$(basename "$dir")
    [ "$DIR_NAME" = "vendor" ] && continue
    IS_KNOWN=false
    for known in "${SUBMODULES[@]}"; do
        if [ "$(basename "$known")" = "$DIR_NAME" ]; then
            IS_KNOWN=true
            break
        fi
    done
    if [ "$IS_KNOWN" = false ]; then
        ALL_SUBMODULES+=("$DIR_NAME")
    fi
done

for submodule in "${ALL_SUBMODULES[@]}"; do
    # An uninitialized/registered-but-not-checked-out submodule is an empty dir
    # with no .git; entering it would make git resolve against the PARENT repo
    # and report the parent's branch/SHAs. Require a .git (file or dir) to skip.
    if [ ! -e "$submodule/.git" ]; then
        echo "⚠️  $submodule — not initialized, skipping"
        continue
    fi

    # Run each submodule's work in a subshell so its cwd is discarded on exit —
    # no manual `cd` restore is needed and an early `exit` cannot corrupt later
    # iterations.
    (
        cd "$submodule" || { echo "⚠️  $submodule — cannot enter directory"; exit 0; }

        branch="$BRANCH"
        if [ -z "$branch" ]; then
            if git show-ref --verify --quiet refs/heads/main 2>/dev/null; then
                branch="main"
            elif git show-ref --verify --quiet refs/heads/master 2>/dev/null; then
                branch="master"
            else
                echo "⚠️  $submodule — no main/master branch found, skipping"
                exit 0
            fi
        fi

        if ! git show-ref --verify --quiet "refs/heads/$branch" 2>/dev/null; then
            echo "⚠️  $submodule — local branch '$branch' does not exist"
            exit 0
        fi

        if ! git remote get-url origin >/dev/null 2>&1; then
            echo "⚠️  $submodule — no 'origin' remote configured"
            exit 0
        fi

        # Refresh remote-tracking state unless --dry-run (fetch is the only
        # network read; --dry-run reports on whatever is already cached).
        if [ "$DRY_RUN" = false ]; then
            if ! git fetch origin "$branch" --quiet 2>/dev/null; then
                echo "⚠️  $submodule — fetch from origin failed; reporting on cached state"
            fi
        fi

        if ! git show-ref --verify --quiet "refs/remotes/origin/$branch" 2>/dev/null; then
            echo "⚠️  $submodule — remote branch 'origin/$branch' not in cache (run without --dry-run to fetch)"
            exit 0
        fi

        local_sha=$(git rev-parse "$branch" 2>/dev/null)
        remote_sha=$(git rev-parse "origin/$branch" 2>/dev/null)
        ahead=$(git rev-list --count "origin/$branch..$branch" 2>/dev/null)
        behind=$(git rev-list --count "$branch..origin/$branch" 2>/dev/null)

        if [ "$local_sha" = "$remote_sha" ]; then
            echo "✅ $submodule ($branch) — synced (${local_sha:0:12})"
        else
            detail=""
            [ "${ahead:-0}" -gt 0 ] 2>/dev/null && detail="${detail}${ahead} unpushed"
            [ "${behind:-0}" -gt 0 ] 2>/dev/null && { [ -n "$detail" ] && detail="${detail}, "; detail="${detail}${behind} behind remote"; }
            [ -z "$detail" ] && detail="diverged"
            echo "❌ $submodule ($branch) — local: ${local_sha:0:12} remote: ${remote_sha:0:12} [$detail]"
        fi

        # --push: push local-ahead commits to the remote. Does NOT pull — that is
        # `git submodule update`'s job. Only act when actually on the target
        # branch — pushing from a detached HEAD or a different checked-out branch
        # would be wrong.
        if [ "$MODE" = push ]; then
            current_branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null)
            if [ "$current_branch" != "$branch" ]; then
                echo "⚠️  $submodule — currently on '$current_branch', not '$branch'; skipping push"
            elif [ "${ahead:-0}" -gt 0 ] 2>/dev/null; then
                if [ "$DRY_RUN" = true ]; then
                    echo "🔍 $submodule ($branch) — would push ${ahead} commit(s) to origin"
                else
                    echo "⬆️  $submodule ($branch) — pushing ${ahead} commit(s) to origin"
                    git push origin "$branch" --quiet 2>/dev/null || echo "⚠️  $submodule — push failed (rejected or conflicts)"
                fi
            fi
        fi

        # --update / --sync: rebase the local branch onto origin/<branch> to catch
        # up on "behind" commits. Both rebase; they differ only in the final HEAD:
        #   --update restores the original checkout (superproject gitlink unchanged)
        #   --sync    stays on the updated branch tip (checkout moves to newer code)
        if [ "$MODE" = update ] || [ "$MODE" = sync ]; then
            if [ "${behind:-0}" -le 0 ] 2>/dev/null; then
                :   # nothing behind to catch up to; leave as-is
            elif ! git diff --quiet 2>/dev/null || ! git diff --cached --quiet 2>/dev/null; then
                echo "⚠️  $submodule — working tree not clean; skipping $MODE"
            elif [ "$DRY_RUN" = true ]; then
                echo "🔍 $submodule ($branch) — would rebase ${behind} commit(s) onto origin/$branch ($MODE)"
            else
                # Remember where HEAD is so --update can return to it. Prefer the
                # symbolic branch name; fall back to the raw SHA for a detached HEAD.
                orig_head=$(git symbolic-ref -q --short HEAD 2>/dev/null || git rev-parse HEAD 2>/dev/null)
                if git checkout --quiet "$branch" 2>/dev/null && git rebase --quiet "origin/$branch" 2>/dev/null; then
                    if [ "$MODE" = update ] && [ "$orig_head" != "$branch" ]; then
                        git checkout --quiet "$orig_head" 2>/dev/null
                        echo "🔄 $submodule ($branch) — rebased ${behind} commit(s) onto origin/$branch; HEAD restored"
                    else
                        echo "🔄 $submodule ($branch) — rebased ${behind} commit(s) onto origin/$branch; now on $branch"
                    fi
                else
                    git rebase --abort 2>/dev/null
                    git checkout --quiet "$orig_head" 2>/dev/null
                    echo "⚠️  $submodule ($branch) — rebase onto origin/$branch failed (conflicts); left untouched"
                fi
            fi
        fi
    )
done
