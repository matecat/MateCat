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

# --- --update --dry-run: reports, mutates nothing ---
fx=$(make_fixture)
before_local=$(git -C "$fx/plugin" rev-parse master)
before_head=$(git -C "$fx/plugin" rev-parse HEAD)
out=$(run_script "$fx" --update --dry-run)
assert_contains "$out" "would rebase 1 commit" "--update --dry-run reports the rebase"
assert_eq "$(git -C "$fx/plugin" rev-parse master)" "$before_local" "--update --dry-run left master ref unchanged"
assert_eq "$(git -C "$fx/plugin" rev-parse HEAD)" "$before_head" "--update --dry-run left HEAD unchanged"

# --- --update: advances master to origin/master, restores detached HEAD ---
fx=$(make_fixture)
before_head=$(git -C "$fx/plugin" rev-parse HEAD)
remote_master=$(git -C "$fx/plugin" rev-parse origin/master)
out=$(run_script "$fx" --update)
assert_eq "$(git -C "$fx/plugin" rev-parse master)" "$remote_master" "--update advanced local master to origin/master"
assert_eq "$(git -C "$fx/plugin" rev-parse HEAD)" "$before_head" "--update restored original detached HEAD"
# HEAD must be detached (not on master) after --update
head_ref=$(git -C "$fx/plugin" symbolic-ref -q --short HEAD || echo DETACHED)
assert_eq "$head_ref" "DETACHED" "--update left HEAD detached (checkout pinned)"

# --- --update skips a dirty working tree ---
fx=$(make_fixture)
touch "$fx/plugin/dirty.txt"; git -C "$fx/plugin" add dirty.txt
out=$(run_script "$fx" --update)
assert_contains "$out" "working tree not clean" "--update skips dirty submodule"

# --- --sync: rebases AND leaves checkout on the branch tip ---
fx=$(make_fixture)
remote_master=$(git -C "$fx/plugin" rev-parse origin/master)
out=$(run_script "$fx" --sync)
assert_contains "$out" "now on master" "--sync reports ending on the branch"
assert_eq "$(git -C "$fx/plugin" rev-parse master)" "$remote_master" "--sync advanced local master to origin/master"
assert_eq "$(git -C "$fx/plugin" rev-parse HEAD)" "$remote_master" "--sync HEAD now at branch tip"
head_ref=$(git -C "$fx/plugin" symbolic-ref -q --short HEAD || echo DETACHED)
assert_eq "$head_ref" "master" "--sync left HEAD on master (checkout moved)"

# --- --sync --dry-run: reports, mutates nothing ---
fx=$(make_fixture)
before_head=$(git -C "$fx/plugin" rev-parse HEAD)
out=$(run_script "$fx" --sync --dry-run)
assert_contains "$out" "would rebase 1 commit" "--sync --dry-run reports the rebase"
assert_eq "$(git -C "$fx/plugin" rev-parse HEAD)" "$before_head" "--sync --dry-run left HEAD unchanged"

# --- a fully-synced submodule triggers no action under --update/--sync ---
fx=$(make_fixture)
git -C "$fx/plugin" branch -f master origin/master   # make it already up to date
git -C "$fx/plugin" checkout -q --detach origin/master
out=$(run_script "$fx" --update)
assert_contains "$out" "synced" "--update on already-synced plugin reports synced"
[[ "$out" != *"rebased"* ]] && echo "✅ PASS: --update no-op when nothing behind" && PASS=$((PASS+1)) || { echo "❌ FAIL: --update acted when nothing behind"; FAIL=$((FAIL+1)); }

# --- -h / --help print usage and exit 0 without touching submodules ---
fx=$(make_fixture)
before_local=$(git -C "$fx/plugin" rev-parse master)
out=$(run_script "$fx" --help); RC=$?
assert_eq "$RC" "0" "--help exits 0"
assert_contains "$out" "Usage:" "--help prints Usage header"
assert_contains "$out" "--update" "--help documents --update"
assert_contains "$out" "--sync" "--help documents --sync"
assert_eq "$(git -C "$fx/plugin" rev-parse master)" "$before_local" "--help performed no submodule work"

out=$(run_script "$fx" -h); RC=$?
assert_eq "$RC" "0" "-h exits 0"
assert_contains "$out" "Usage:" "-h prints Usage header"

# --- --update on a genuine rebase conflict: aborts and restores original state ---
# make_fixture's c1/c2 are --allow-empty, so rebasing never conflicts there;
# build a dedicated fixture where local and origin diverge on the SAME file/line.
make_conflict_fixture() {
    local root remote plugin base
    root=$(mktemp -d "$WORK/superconflict.XXXXXX")
    remote="$root/remote.git"
    plugin="$root/plugin"

    git init -q --bare "$remote"

    git init -q "$plugin"
    git -C "$plugin" -c user.email=t@t -c user.name=t commit -q --allow-empty -m base0
    printf 'line1\n' > "$plugin/f.txt"
    git -C "$plugin" add f.txt
    git -C "$plugin" -c user.email=t@t -c user.name=t commit -q -m base_file
    git -C "$plugin" branch -M master
    git -C "$plugin" remote add origin "$remote"
    git -C "$plugin" push -q origin master
    base=$(git -C "$plugin" rev-parse HEAD)

    # Diverge on origin: edit f.txt one way, push as the new remote tip (c2).
    printf 'line1\nremote-change\n' > "$plugin/f.txt"
    git -C "$plugin" add f.txt
    git -C "$plugin" -c user.email=t@t -c user.name=t commit -q -m c2_remote
    git -C "$plugin" push -q origin master

    # Diverge locally from the same base: edit the same line differently (c1).
    git -C "$plugin" reset -q --hard "$base"
    printf 'line1\nlocal-change\n' > "$plugin/f.txt"
    git -C "$plugin" add f.txt
    git -C "$plugin" -c user.email=t@t -c user.name=t commit -q -m c1_local
    git -C "$plugin" fetch -q origin master           # cache origin/master @ c2_remote
    git -C "$plugin" checkout -q --detach master       # detached HEAD at local c1_local

    git init -q "$root"
    git -C "$root" -c user.email=t@t -c user.name=t commit -q --allow-empty -m super

    echo "$root"
}

fx=$(make_conflict_fixture)
before_head=$(git -C "$fx/plugin" rev-parse HEAD)
before_local=$(git -C "$fx/plugin" rev-parse master)
out=$(run_script "$fx" --update)
assert_contains "$out" "failed (conflicts)" "--update on real conflict reports failed (conflicts)"
assert_eq "$(git -C "$fx/plugin" rev-parse HEAD)" "$before_head" "--update on conflict restored original HEAD"
assert_eq "$(git -C "$fx/plugin" rev-parse master)" "$before_local" "--update on conflict left local master ref unadvanced"
if [ -e "$fx/plugin/.git/rebase-merge" ] || [ -e "$fx/plugin/.git/rebase-apply" ]; then
    echo "❌ FAIL: --update on conflict leaves no rebase in progress"
    FAIL=$((FAIL + 1))
else
    echo "✅ PASS: --update on conflict leaves no rebase in progress"
    PASS=$((PASS + 1))
fi

# --- explicit branch argument flows through to the action (not just auto-detect) ---
# Give the plugin a second branch, "feature", behind its own origin/feature, and
# pass it as the trailing positional arg while master is left untouched.
fx=$(make_fixture)
git -C "$fx/plugin" checkout -q -b feature origin/master
git -C "$fx/plugin" -c user.email=t@t -c user.name=t commit -q --allow-empty -m feature_base
git -C "$fx/plugin" push -q origin feature                # remote feature @ feature_base
feature_base=$(git -C "$fx/plugin" rev-parse feature)
git -C "$fx/plugin" -c user.email=t@t -c user.name=t commit -q --allow-empty -m feature_ahead
git -C "$fx/plugin" push -q origin feature                # remote feature advances further
remote_feature=$(git -C "$fx/plugin" rev-parse origin/feature)
git -C "$fx/plugin" reset -q --hard "$feature_base"        # local feature falls behind
git -C "$fx/plugin" fetch -q origin feature
git -C "$fx/plugin" checkout -q --detach "$feature_base"
before_master=$(git -C "$fx/plugin" rev-parse master)

out=$(run_script "$fx" --update feature)
assert_eq "$(git -C "$fx/plugin" rev-parse feature)" "$remote_feature" \
    "explicit branch arg: --update advanced 'feature' to origin/feature"
assert_eq "$(git -C "$fx/plugin" rev-parse master)" "$before_master" \
    "explicit branch arg: --update left unrelated 'master' untouched"
assert_contains "$out" "rebased 1 commit(s) onto origin/feature" \
    "explicit branch arg: output names the passed-in branch, not master"

echo ""
echo "Passed: $PASS  Failed: $FAIL"
[ "$FAIL" -eq 0 ]
