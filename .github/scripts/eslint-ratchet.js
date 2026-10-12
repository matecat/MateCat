'use strict'

/**
 * ESLint ratchet.
 *
 * The frontend carries a lint backlog that predates any CI enforcement, so a
 * plain `eslint .` cannot gate a build — it would fail every one of them. This
 * lints the base branch and the merge result and fails on the violations the
 * branch adds, which blocks new problems without demanding the old ones be
 * fixed first.
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
const SEP = '\u0000'
const MAX_REPORTED = 30

/** Is this file part of the gated set? `relativePath` is relative to the tree root. */
function isCounted(relativePath) {
  const normalised = relativePath.split(path.sep).join('/')
  return !UNCOUNTED_PREFIXES.some((prefix) => normalised.startsWith(prefix))
}

/**
 * Index every violation by file, rule and message text.
 *
 * Keyed on the message rather than the line, because lines move as soon as
 * anything above them is edited and untouched violations would then be reported
 * as new. The message text stays put and names the identifier at fault, so it
 * survives the file being reformatted around it.
 *
 * Returns `{key: {file, rule, message, count, locations}}`.
 */
function indexViolations(results, cwd) {
  const index = {}

  for (const result of results) {
    const file = path.relative(cwd, result.filePath).split(path.sep).join('/')
    if (!isCounted(file)) continue

    for (const message of result.messages) {
      const rule = message.ruleId || '(parse error)'
      const key = [file, rule, message.message].join(SEP)

      if (!index[key]) {
        index[key] = {
          file,
          rule,
          message: message.message,
          count: 0,
          locations: [],
        }
      }

      index[key].count++
      index[key].locations.push({line: message.line, column: message.column})
    }
  }

  return index
}

/** Total violations in an index. */
function totalOf(index) {
  return Object.values(index).reduce((sum, entry) => sum + entry.count, 0)
}

/**
 * The violations this branch adds, worst first.
 *
 * Where a file gained N copies of an identical message, the last N locations
 * are reported: which specific one is new is unknowable, and the ones furthest
 * down the file are the likeliest.
 */
function addedViolations(baseIndex, headIndex) {
  return Object.keys(headIndex)
    .map((key) => {
      const head = headIndex[key]
      const wasThere = baseIndex[key] ? baseIndex[key].count : 0
      const by = head.count - wasThere

      return {...head, by, locations: head.locations.slice(-Math.max(by, 0))}
    })
    .filter((entry) => entry.by > 0)
    .sort((a, b) => b.by - a.by || a.file.localeCompare(b.file))
}

/** One line per location, in the shape an editor can jump to. */
function formatAdded(added) {
  const lines = []

  for (const entry of added) {
    for (const where of entry.locations) {
      lines.push(
        `  ${entry.file}:${where.line}:${where.column}  ${entry.rule}  ${entry.message}`,
      )
    }
  }

  return lines
}

/**
 * Decide the outcome. Pure, so the tests can cover it without running ESLint.
 *
 * Added violations fail the run even when the total falls. Comparing totals
 * alone would let a branch that clears three hundred problems introduce new
 * ones for free, which is the behaviour this is meant to stop.
 */
function evaluate({base, head, added = []}) {
  if (added.length) {
    const headline =
      head > base
        ? `The total rose from ${base} to ${head} (+${head - base}). This branch adds:`
        : `The total fell from ${base} to ${head}, but this branch adds:`

    const lines = formatAdded(added)
    const shown = lines.slice(0, MAX_REPORTED)
    const rest =
      lines.length > shown.length
        ? `\n  ...and ${lines.length - shown.length} more`
        : ''

    return {
      ok: false,
      message:
        `${headline}\n${shown.join('\n')}${rest}\n\n` +
        'Fix these, or say in the pull request why they are intended. ' +
        'A moved file counts as new, so a rename shows up here.',
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

/** Lint one tree and index it. `configFile` is the head's config. */
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

  return indexViolations(results, dir)
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
    added: addedViolations(base, head),
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
  addedViolations,
  evaluate,
  formatAdded,
  indexViolations,
  isCounted,
  totalOf,
}
