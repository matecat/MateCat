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
# Echoes combined stdout+stderr; caller must capture the exit code via `$?`
# immediately after the command substitution (a global RC set inside this
# function would be scoped to the substitution's subshell and lost).
run_script() {
    local root="$1"; shift
    local rc
    (cd "$root" && bash "$CHECK_SYNC" "$@" 2>&1)
    rc=$?
    return "$rc"
}

# --- smoke test: fixture reports the plugin as behind ---
fx=$(make_fixture)
out=$(run_script "$fx"); RC=$?
assert_contains "$out" "1 behind remote" "smoke: fixture plugin reported 1 behind remote"

# --- mutual exclusivity ---
fx=$(make_fixture)
out=$(run_script "$fx" --push --update); RC=$?
assert_eq "$RC" "1" "mutex: --push --update exits 1"
assert_contains "$out" "mutually exclusive" "mutex: --push --update explains why"

out=$(run_script "$fx" --update --sync); RC=$?
assert_eq "$RC" "1" "mutex: --update --sync exits 1"

# --- unknown flag still rejected ---
out=$(run_script "$fx" --bogus); RC=$?
assert_eq "$RC" "1" "unknown flag --bogus exits 1"
assert_contains "$out" "Unknown flag" "unknown flag names itself"

# --- new flags accepted (no action block yet: normal status still printed) ---
out=$(run_script "$fx" --update --dry-run); RC=$?
assert_eq "$RC" "0" "--update --dry-run exits 0"

echo ""
echo "Passed: $PASS  Failed: $FAIL"
[ "$FAIL" -eq 0 ]
