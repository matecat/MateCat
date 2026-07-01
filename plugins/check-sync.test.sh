#!/bin/bash
#
# check-sync.test.sh — fixture-driven tests for check-sync.sh.
# Builds throwaway git repos in a temp dir; no network, no submodule registration.
# Run: bash plugins/check-sync.test.sh
#
set -u

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
CHECK_SYNC="$SCRIPT_DIR/check-sync.sh"

WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT

PASS=0
FAIL=0

# Assert that "$1" (haystack) contains "$2" (needle); $3 is the test label.
assert_contains() {
    if [[ "$1" == *"$2"* ]]; then
        echo "✅ PASS: $3"
        PASS=$((PASS + 1))
    else
        echo "❌ FAIL: $3"
        echo "   expected to find: $2"
        echo "   in output:"
        echo "$1" | sed 's/^/     | /'
        FAIL=$((FAIL + 1))
    fi
}

# Assert string equality; $3 is the test label.
assert_eq() {
    if [ "$1" = "$2" ]; then
        echo "✅ PASS: $3"
        PASS=$((PASS + 1))
    else
        echo "❌ FAIL: $3 (expected '$2', got '$1')"
        FAIL=$((FAIL + 1))
    fi
}

# Build a superproject containing plugin/ whose local master is 1 commit behind
# origin/master, with HEAD detached at the old (c1) commit. Echoes the root path.
make_fixture() {
    local root remote plugin
    root=$(mktemp -d "$WORK/super.XXXXXX")
    remote="$root/remote.git"
    plugin="$root/plugin"

    git init -q --bare "$remote"

    git init -q "$plugin"
    git -C "$plugin" -c user.email=t@t -c user.name=t commit -q --allow-empty -m c1
    git -C "$plugin" branch -M master
    git -C "$plugin" remote add origin "$remote"
    git -C "$plugin" push -q origin master           # remote @ c1
    local c1; c1=$(git -C "$plugin" rev-parse HEAD)
    git -C "$plugin" -c user.email=t@t -c user.name=t commit -q --allow-empty -m c2
    git -C "$plugin" push -q origin master           # remote @ c2
    git -C "$plugin" reset -q --hard "$c1"           # local master back to c1 (behind 1)
    git -C "$plugin" fetch -q origin master          # cache origin/master @ c2
    git -C "$plugin" checkout -q --detach "$c1"      # detached HEAD at c1 (the "gitlink")

    # Make the superproject a real repo so `git rev-parse --show-toplevel` works.
    git init -q "$root"
    git -C "$root" -c user.email=t@t -c user.name=t commit -q --allow-empty -m super

    echo "$root"
}

# Run check-sync.sh from inside a fixture root. Args are forwarded to the script.
# Echoes combined stdout+stderr; sets global RC to the exit code.
run_script() {
    local root="$1"; shift
    local out
    out=$(cd "$root" && bash "$CHECK_SYNC" "$@" 2>&1)
    RC=$?
    echo "$out"
}

# --- smoke test: fixture reports the plugin as behind ---
fx=$(make_fixture)
out=$(run_script "$fx")
assert_contains "$out" "1 behind remote" "smoke: fixture plugin reported 1 behind remote"

echo ""
echo "Passed: $PASS  Failed: $FAIL"
[ "$FAIL" -eq 0 ]
