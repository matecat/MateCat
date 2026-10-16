---
name: deploy-matecat
description: Release a new version of MateCat to production — merge develop into master, verify the submodule gitlinks, bump the version with yarn, push master and the vX.Y.Z tag, then follow the CI/CD run to green. Use when asked "deploy Matecat", "deploy the next version of Matecat", "deploy the next minor/major [version of] Matecat", or "deploy Matecat 4.1.2" / "deploy Matecat v4.1.2". NOT for deploying to develop (Freddy) — a push to develop deploys itself, no ritual needed. NOT for hotfixing a bad release: the tag is public the moment it is pushed, so the remedy is always a follow-up release, never a force-push.
---

# Deploy MateCat

Releases MateCat to production. The whole procedure is cheap to check and expensive
to undo, and the boundary between the two is exactly one command: pushing the
`vX.Y.Z` tag. Everything before that boundary is verified locally; the push itself
waits for an explicit go-ahead.

MateCat uses SemVer (`MAJOR.MINOR.PATCH`). The **only** source of truth for the
current version is `inc/version.ini` on the **remote master branch**.

Work from the repo root:

```bash
cd /home/mauretto78/PhpstormProjects/MateCat
```

## Step 0: resolve and validate the target version

Do this first, before touching a single branch. Read all three version markers off
`origin/master` and require that they agree.

```bash
git fetch origin master develop --tags --prune
git show origin/master:inc/version.ini
git show origin/master:package.json | python3 -c 'import json,sys; print(json.load(sys.stdin)["version"])'
git tag --sort=-v:refname | head -1
```

`CURRENT` is the `inc/version.ini` value with the leading `v` stripped (the ini
stores `version = "v4.1.1"`; `package.json` stores the bare `4.1.1`). If the ini,
`package.json` and the newest tag do not all describe the same version, **abort** —
`yarn version` computes the bump from `package.json`, so a divergence means the next
release would land on the wrong number.

From `CURRENT = M.m.p` there are exactly three legal targets:

| mode | phrasing | target |
|---|---|---|
| patch | `deploy Matecat`, `deploy the next version of Matecat` | `M.m.(p+1)` |
| minor | `deploy the next minor [version of] Matecat` | `M.(m+1).0` |
| major | `deploy the next major [version of] Matecat` | `(M+1).0.0` |
| explicit | `deploy Matecat [v]X.Y.Z` | must **equal** one of the three above |

For an explicit version: strip a leading `v`, require `^[0-9]+\.[0-9]+\.[0-9]+$`,
then check it against the three computed candidates. Anything else **aborts** — print
`CURRENT` and the three allowed values and stop. With `CURRENT = 4.1.1` that means
`3.0.5` (backwards), `4.1.1` (already released), `4.1.5` and `6.0.0` (skips ahead)
all abort; only `4.1.2`, `4.2.0` and `5.0.0` proceed. The strictness is deliberate:
it catches a doubled or transposed digit, which is the realistic typo.

Also abort if the target tag already exists:

```bash
git tag -l "v$TARGET"
git ls-remote --tags origin "refs/tags/v$TARGET"
```

## Step 0b: preflight

Hard stops, all before any branch switch:

```bash
git rev-parse --abbrev-ref HEAD     # remember this; restore it on any abort
git status --porcelain              # must be empty
test -d node_modules/lint-staged || echo "run yarn install first"
```

- **Dirty tree → abort.** List the files and ask the user to commit or stash. Never
  stash on their behalf: this repo routinely carries local modifications to the test
  config, `yarn.lock` and `MateCat-docs`, and those are the user's call.
- **Missing `node_modules` → abort.** `.husky/pre-commit` runs `lint-staged`, so the
  version commit fails without it. (`lint-staged.config.js` only matches `*.js`, so
  it will not actually reformat anything in the release commit — it just has to be
  installed.)

## Steps 1–4: develop

```bash
git checkout develop
git pull origin develop
git submodule update
```

Then run the submodule check below with `REF=origin/develop`.

## Steps 5–10: master, and the merge

```bash
git checkout master
git pull origin master
git submodule update
# submodule check here with REF=origin/master, BEFORE merging
git merge develop
git submodule update
# submodule check again, now with REF=HEAD
```

On a merge conflict: stop, show `git diff --name-only --diff-filter=U`, and hand back
to the user. Do **not** run `git merge --abort` unless they ask for it — that throws
away conflict resolution they may want to do by hand.

## The submodule check (steps 4 and 10)

This is the manual "do my local plugin commit points match what GitHub records?"
eyeball, made mechanical. Enumerate gitlinks from the tree, never from `.gitmodules`
(see Gotchas).

`REF` is whichever ref the working tree is supposed to reflect right now:

| when | `REF` |
|---|---|
| step 4, on develop after the pull | `origin/develop` |
| step 10, on master after the pull, **before** the merge | `origin/master` |
| step 10, on master **after** `git merge develop` | `HEAD` |

That last row matters: the merge pulls develop's gitlinks into the tree, so a
post-merge comparison against `origin/master` would abort on precisely the
submodules this release is bumping. After the merge, the tree's own `HEAD` is the
reference.

```bash
REF=origin/master        # see the table above

git submodule status | awk '/^[-+U]/{print "ABORT dirty submodule: " $0; bad=1}
                            END{if(!bad) print "ok    no -/+/U submodule states"}'
git submodule foreach --quiet 'git fetch --quiet origin'

git ls-tree -r "$REF" | awk '$2=="commit"{print $4, $3}' | while read -r p sha; do
  h=$(git -C "$p" rev-parse HEAD)
  def=$(git -C "$p" symbolic-ref -q refs/remotes/origin/HEAD || echo refs/remotes/origin/master)
  pushed=$(git -C "$p" for-each-ref --contains "$sha" --count=1 \
             --format='%(refname)' refs/remotes/origin 2>/dev/null)
  if [ "$h" != "$sha" ]; then
    echo "ABORT $p: local HEAD ${h:0:12} != $REF gitlink ${sha:0:12}"
  elif [ -z "$pushed" ]; then
    echo "ABORT $p: gitlink ${sha:0:12} is on no remote branch (unpushed)"
  elif ! git -C "$p" merge-base --is-ancestor "$sha" "$def"; then
    echo "WARN  $p: gitlink ${sha:0:12} not on ${def#refs/remotes/} (feature/archive tip)"
  else
    echo "ok    $p ${sha:0:12} on ${def#refs/remotes/}"
  fi
done
```

Four assertions, three of them fatal:

- **`git submodule status` shows `-`, `+` or `U`** → abort. `-` means uninitialized
  (`git submodule update --init --recursive`), `+` means the checked-out commit does
  not match the gitlink, `U` means a conflict.
- **local HEAD != the gitlink recorded on `REF`** → abort. The working copy and what
  GitHub records for that branch have diverged; releasing would ship a pointer that
  was never reviewed.
- **gitlink on no remote branch** → abort. CI checks out with `submodules:
  recursive`, so a commit that exists only locally cannot be fetched. This failure
  would otherwise surface *after* the tag is already public.
- **gitlink not on the submodule's default branch** → **warning only**, carried
  through to the pre-push summary. `internal_scripts` and `plugins/aligner`
  legitimately sit on feature or archive branch tips at times, so this must never
  block a release.

Also collect the bumps this release actually ships, for the summary:

```bash
join -j1 \
  <(git ls-tree -r origin/master  | awk '$2=="commit"{print $4, substr($3,1,12)}' | sort) \
  <(git ls-tree -r origin/develop | awk '$2=="commit"{print $4, substr($3,1,12)}' | sort) \
  | awk '$2!=$3 {printf "%-22s master=%s -> develop=%s\n", $1, $2, $3}'
```

## Step 11: bump the version

`yarn version` is interactive by default; `--new-version` is the non-interactive
equivalent of typing the number at the prompt. This is Yarn 1 (classic).

```bash
yarn version --new-version "$TARGET"
```

The lifecycle in `package.json` does three things: the `version` hook rewrites
`inc/version.ini` via `sed`, yarn commits and tags, then `postversion` amends
`inc/version.ini` into that commit and replaces yarn's lightweight tag with an
annotated one. Verify all of it landed:

```bash
git log -1 --format='%s'      # must be exactly vTARGET
git show --stat HEAD          # must touch ONLY inc/version.ini and package.json
cat inc/version.ini           # version = "vTARGET"
git rev-list -1 "v$TARGET"    # must equal git rev-parse HEAD
```

If the release commit touches anything beyond those two files, stop — something
unrelated got swept in.

## Step 12: push (stop and ask first)

**Pushing the tag deploys to production. Always stop here and get an explicit
go-ahead**, even when every check above is green. Present:

- `CURRENT` → `TARGET`, and which mode produced it
- what develop is bringing in: `git log --oneline origin/master..HEAD`
- the submodule gitlink bumps being released, plus any `WARN` lines from the check
- whether master carries commits that develop does not (backmerge advisory):
  `git log --oneline origin/develop..HEAD --no-merges`
- the two exact commands about to run

Only after approval:

```bash
git push origin master
git push origin master --tags
```

Both are required. The first alone runs tests and deploys nothing.

## Step 13: follow CI

The release commit and the tag point at the same SHA, so one query covers every run:

```bash
SHA=$(git rev-parse HEAD)
gh run list --commit "$SHA" --limit 10 \
  --json databaseId,name,status,conclusion,headBranch,event \
  -q '.[] | [.headBranch, .name, .status, .conclusion, (.databaseId|tostring)] | @tsv'
```

Expect four runs, distinguished by `headBranch`:

| headBranch | workflow | what it does |
|---|---|---|
| `master` | CI/CD: Production | tests only — `should_deploy` is false off a branch |
| `master` | PHPStan | static analysis |
| `master` | PHPMD | mess detection, SARIF upload |
| `vTARGET` | CI/CD: Production | tests, **then the production deploy** |

Runs need a few seconds to register — poll until the `vTARGET` run appears rather
than concluding it is missing. Then:

```bash
gh run watch <id> --exit-status
gh run view <id> --json jobs -q '.jobs[] | [.name, .status, .conclusion] | @tsv'
```

Jobs are prefixed `ci-cd / ` (the reusable workflow's caller job). On the `vTARGET`
run expect `ci-cd / Run tests` → success and **`ci-cd / Deploy to production` →
success**; `ci-cd / Test adequacy gate` is `skipped` (it is PR-only) and that is
correct, not a problem. On the `master` run the same job appears unexpanded as
`ci-cd / Deploy to ${{ inputs.deploy_environment }}` and is `skipped` — that is the
visible proof that a branch push deploys nothing.

Green means the `vTARGET` run's `ci-cd / Deploy to production` concluded `success`,
and PHPStan and PHPMD on master are green too. A `waiting` or `action_required`
status on the deploy job means the `production` GitHub environment is holding for
manual approval — say so and keep waiting; that is not a failure.

## Step 14: report

State plainly:

- the released version, the release commit SHA and the tag
- each run ID and its conclusion
- the ECR image tag the deploy produced: `build-vTARGET-<run_number>` (the `deploy`
  job reads `inc/version.ini` with `awk`, which keeps the `v`)
- any `WARN` carried through from the submodule check

If CI failed, report the failing job and the relevant log excerpt. The tag is already
public, so the remedy is a follow-up release — never a force-push or a re-tag.

## Gotchas

- **Only the tag deploys.** `aws_prod.yml` fires on both the master push and the tag,
  but passes `should_deploy: startsWith(github.ref, 'refs/tags/v')`. Skipping
  `git push origin master --tags` produces a green CI run and no deployment.
- **`.gitmodules` has stale entries.** It declares `plugins/familysearch` and
  `plugins/roblox`, but neither has a gitlink in the master or develop tree — only 8
  of the 10 are real. A check driven off `.gitmodules` invents failures; drive it off
  `git ls-tree`.
- **Submodule default branches differ.** `MateCat-docs` and `docker` default to
  `main`, the rest to `master`. Resolve `origin/HEAD` per submodule instead of
  assuming.
- **Every annotated tag's message is the literal string `v${npm_package_version}`.**
  The `postversion` script single-quotes `-m'...'`, so the shell never expands it.
  Cosmetic, pre-existing on every release, and a one-character fix — but it is
  release tooling, so fix it in its own commit, never mid-release.
- **`gh pr checks --json` is unsupported** by the installed `gh` (2.45) and prints
  nothing while exiting 0. `gh run list --json` and `gh run list --commit` are both
  supported and verified — use those.
- **Do not build these checks on `grep` exit status.** Depending on the shell these
  commands run in, `grep` may be a function shimming to `ugrep`, whose `-qv` exit
  status differs from GNU grep (`printf 'aaa\nbbb\n' | grep -qv aaa` exits 1, not
  0). An earlier version of the submodule check used `git branch -r --contains ... |
  grep -qv` and reported every submodule as unpushed. The block above uses `git
  for-each-ref --contains` and `awk` instead, which behave identically either way.
- **Never `git commit -a` here.** It stages every modified tracked file, ignoring any
  prior `git add`. `yarn version` does the committing; there is nothing to patch up
  by hand.
- **The merge can leave master ahead of develop**, when a hotfix PR was merged
  straight into master. Worth a `master` → `develop` backmerge afterwards, but that
  is a separate task, not part of this procedure.
- **Restore the caller's original branch on any abort.** The procedure starts by
  checking out `develop`, and leaving them stranded there is its own small mess.
- **`.claude/settings.json` denies the ini config files** under `inc/`. Quoting that
  deny glob verbatim in a command is enough to get the command itself blocked.
  `inc/version.ini` does not match the pattern and is fine to read.

## If something here doesn't match reality

Verified against the repo on 2026-08-26, at `v4.1.1`. The source-of-truth files are:

- `inc/version.ini` on `origin/master` — the current version
- `package.json` — the `version` field and the `preversion`/`version`/`postversion`
  scripts
- `.github/workflows/aws_prod.yml` — triggers and the `should_deploy` expression
- `.github/workflows/_ci-cd.yml` — the `tests` / `deploy` / `test-guard` /
  `slack-notification` jobs, the tag-format gate and the ECR tagging
- `.gitmodules` and `git ls-tree -r origin/master` — declared vs. actual submodules

Re-read those before trusting anything above, and update this skill when they have
moved on.
