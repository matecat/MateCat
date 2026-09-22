'use strict'

/**
 * ESLint ratchet.
 *
 * The frontend carries a lint backlog that predates any CI enforcement, so a
 * plain `eslint .` cannot gate a build — it would fail every one of them. This
 * lints the base branch and the merge result and fails only when the merge
 * result has more problems, which blocks new ones without demanding the old
 * ones be fixed first.
 *
 * There is deliberately no committed baseline number. One would have to be
 * edited every time the count moved, and — because a pull request is checked
 * out as its merge with the base — anything the base branch added would land on
 * the pull request's score.
 *
 * Both trees are linted with the *head's* configuration. The rules themselves
 * are part of what a branch may change, and counting two trees under two
 * different rule sets compares nothing.
 *
 * `plugins/` is left out. Those are SSH git submodules, so whether they are
 * present depends on the checkout having deploy keys, and a total that changes
 * with the checkout cannot gate anything.
 */

const path = require('path')

const LINT_TARGETS = ['.']
const UNCOUNTED_PREFIXES = ['plugins/']

/** Is this file part of the gated set? `relativePath` is relative to the tree root. */
function isCounted(relativePath) {
  const normalised = relativePath.split(path.sep).join('/')
  return !UNCOUNTED_PREFIXES.some((prefix) => normalised.startsWith(prefix))
}

/**
 * Counts keyed by `file\u0000rule`.
 *
 * Per-file, not per-rule totals: this branch clears far more `no-unused-vars`
 * than it adds, so a repo-wide total for that rule stays below the base even
 * when a new file introduces some. Comparing each file separately is what makes
 * a newly added violation visible.
 */
function countByFileRule(results, cwd) {
  const counts = {}

  for (const result of results) {
    const rel = path.relative(cwd, result.filePath).split(path.sep).join('/')
    if (!isCounted(rel)) continue

    for (const message of result.messages) {
      const key = `${rel}\u0000${message.ruleId || '(parse error)'}`
      counts[key] = (counts[key] || 0) + 1
    }
  }

  return counts
}

/** Where a file gained violations of a rule. Worst first. */
function risenEntries(baseCounts, headCounts) {
  return Object.keys(headCounts)
    .map((key) => {
      const [file, rule] = key.split('\u0000')
      return {file, rule, by: headCounts[key] - (baseCounts[key] || 0)}
    })
    .filter((entry) => entry.by > 0)
    .sort((a, b) => b.by - a.by || a.file.localeCompare(b.file))
}

/** Total across a counts map. */
function totalOf(counts) {
  return Object.values(counts).reduce((sum, n) => sum + n, 0)
}

/**
 * Decide the outcome. Pure, so the tests can cover it without running ESLint.
 *
 * Any file that gains violations fails the run, even when the total falls.
 * Comparing only totals would let a branch that clears three hundred problems
 * introduce new ones for free, which is the behaviour this is meant to stop.
 */
function evaluate({base, head, risen = []}) {
  if (risen.length) {
    const headline =
      head > base
        ? `The total rose from ${base} to ${head} (+${head - base}).`
        : `The total fell from ${base} to ${head}, but these gained violations:`

    const shown = risen.slice(0, 20)
    const rest =
      risen.length > shown.length
        ? `\n  ...and ${risen.length - shown.length} more`
        : ''

    return {
      ok: false,
      message:
        `${headline}\n` +
        shown.map((r) => `  +${r.by}  ${r.rule}  ${r.file}`).join('\n') +
        rest +
        '\nFix what this branch added, or say in the pull request why the rise ' +
        'is intended. A moved file counts as new, so a rename shows up here.',
    }
  }

  if (head < base) {
    return {
      ok: true,
      message: `ESLint problems fell from ${base} to ${head} (-${base - head}).`,
    }
  }

  return {ok: true, message: `ESLint problems unchanged at ${base}.`}
}

/** Lint one tree and return its totals. `configFile` is the head's config. */
async function lintTree(dir, configFile) {
  const {ESLint} = require('eslint')

  const results = await new ESLint({
    cwd: dir,
    // The head's config, and only it: `useEslintrc: false` stops the tree's own
    // .eslintrc.js cascading in, which for the base tree is the old rule set.
    overrideConfigFile: configFile,
    useEslintrc: false,
    // The base checkout has no node_modules of its own.
    resolvePluginsRelativeTo: path.dirname(configFile),
    ignorePath: path.join(dir, '.gitignore'),
  }).lintFiles(LINT_TARGETS)

  return countByFileRule(results, dir)
}

async function main() {
  const [headDir, baseDir] = process.argv.slice(2)

  if (!headDir || !baseDir) {
    console.error('usage: eslint-ratchet.js <head-dir> <base-dir>')
    process.exitCode = 1
    return
  }

  // The head's rules judge both trees.
  const configFile = path.join(path.resolve(headDir), '.eslintrc.js')

  const head = await lintTree(path.resolve(headDir), configFile)
  const base = await lintTree(path.resolve(baseDir), configFile)

  const outcome = evaluate({
    base: totalOf(base),
    head: totalOf(head),
    risen: risenEntries(base, head),
  })

  console.log(outcome.message)
  if (!outcome.ok) process.exitCode = 1
}

if (require.main === module) {
  main().catch((error) => {
    console.error(error)
    process.exitCode = 1
  })
}

module.exports = {
  countByFileRule,
  totalOf,
  evaluate,
  isCounted,
  risenEntries,
}
