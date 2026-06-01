#!/bin/bash
#
# check-sync.sh — Verify submodule branches are aligned with their remotes.
#
# Run from the plugins/ directory. Iterates over all git submodules and checks
# whether the local branch matches the remote (origin) branch. Reports synced,
# ahead/behind, or errors (missing branch, missing remote, etc.).
#
# Usage:
#   bash check-sync.sh [branch]
#
# Arguments:
#   branch   Optional. The branch name to check (e.g., context-review, develop).
#            If omitted, auto-detects main or master per submodule.
#
# Examples:
#   bash check-sync.sh                  # check main/master alignment
#   bash check-sync.sh context-review   # check context-review alignment
#
# Output:
#   ✅ plugin (branch) — synced (sha)
#   ❌ plugin (branch) — local: sha remote: sha [+ahead/-behind]
#   ⚠️  plugin — error description
#

BRANCH="${1:-}"

for plugin in */; do
    plugin="${plugin%/}"
    [ ! -e "$plugin/.git" ] && continue
    cd "$plugin" || { echo "⚠️  $plugin — cannot enter directory"; continue; }

    branch="$BRANCH"
    if [ -z "$branch" ]; then
        if git show-ref --verify --quiet refs/heads/main 2>/dev/null; then
            branch="main"
        elif git show-ref --verify --quiet refs/heads/master 2>/dev/null; then
            branch="master"
        else
            echo "⚠️  $plugin — no main/master branch found, skipping"
            cd ..
            continue
        fi
    fi

    if ! git show-ref --verify --quiet "refs/heads/$branch" 2>/dev/null; then
        echo "⚠️  $plugin — local branch '$branch' does not exist"
        cd ..
        continue
    fi

    if ! git remote get-url origin &>/dev/null; then
        echo "⚠️  $plugin — no 'origin' remote configured"
        cd ..
        continue
    fi

    git fetch origin "$branch" --quiet 2>/dev/null
    if ! git show-ref --verify --quiet "refs/remotes/origin/$branch" 2>/dev/null; then
        echo "⚠️  $plugin — remote branch 'origin/$branch' does not exist"
        cd ..
        continue
    fi

    local_sha=$(git rev-parse "$branch" 2>/dev/null)
    remote_sha=$(git rev-parse "origin/$branch" 2>/dev/null)

    if [ "$local_sha" = "$remote_sha" ]; then
        echo "✅ $plugin ($branch) — synced (${local_sha:0:12})"
    else
        ahead=$(git rev-list --count "origin/$branch..$branch" 2>/dev/null)
        behind=$(git rev-list --count "$branch..origin/$branch" 2>/dev/null)
        detail=""
        [ "$ahead" -gt 0 ] 2>/dev/null && detail="${detail}${ahead} unpushed"
        [ "$behind" -gt 0 ] 2>/dev/null && { [ -n "$detail" ] && detail="${detail}, "; detail="${detail}${behind} behind remote"; }
        echo "❌ $plugin ($branch) — local: ${local_sha:0:12} remote: ${remote_sha:0:12} [$detail]"
    fi

    cd ..
done
