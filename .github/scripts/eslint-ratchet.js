'use strict'

/**
 * ESLint ratchet.
 *
 * The frontend carries a lint backlog that predates any CI enforcement, so a
 * plain `eslint .` cannot gate a build — it would fail every one of them. This
 * compares the total number of problems against a committed baseline instead: a
 * branch may lower it or leave it alone, never raise it.
 *
 * When the total drops, the run still passes and prints the new number. Lower
 * `maxProblems` in the baseline file to lock the improvement in.
 *
 * `plugins/` is left out of the count. Those are SSH git submodules, so whether
 * they are present depends on the checkout having deploy keys, and a total that
 * changes with the checkout cannot gate anything.
 */

const fs = require('fs')
const path = require('path')

const BASELINE_FILE = path.join(__dirname, '..', 'eslint-baseline.json')

const LINT_TARGETS = ['.']
const UNCOUNTED_PREFIXES = ['plugins/']

/** Is this file part of the gated set? `relativePath` is relative to the repo root. */
function isCounted(relativePath) {
  const normalised = relativePath.split(path.sep).join('/')
  return !UNCOUNTED_PREFIXES.some((prefix) => normalised.startsWith(prefix))
}

/** Total errors + warnings across the gated files. */
function countProblems(results, cwd) {
  return results.reduce((total, result) => {
    if (!isCounted(path.relative(cwd, result.filePath))) return total
    return total + result.errorCount + result.warningCount
  }, 0)
}

function readBaseline(file = BASELINE_FILE) {
  const parsed = JSON.parse(fs.readFileSync(file, 'utf8'))
  const {maxProblems} = parsed

  if (!Number.isInteger(maxProblems) || maxProblems < 0) {
    throw new Error(`${file}: maxProblems must be a non-negative integer`)
  }

  return maxProblems
}

/** Decide the outcome. Pure, so the tests can cover it without running ESLint. */
function evaluate({count, baseline}) {
  if (count > baseline) {
    return {
      ok: false,
      message:
        `ESLint problems rose from ${baseline} to ${count} (+${count - baseline}).\n` +
        'Fix what this branch added, or explain the rise and raise maxProblems in ' +
        '.github/eslint-baseline.json deliberately.',
    }
  }

  if (count < baseline) {
    return {
      ok: true,
      message:
        `ESLint problems fell from ${baseline} to ${count} (-${baseline - count}).\n` +
        'Set maxProblems to ' +
        count +
        ' in .github/eslint-baseline.json to keep the gain.',
    }
  }

  return {ok: true, message: `ESLint problems unchanged at ${baseline}.`}
}

/**
 * Per-rule totals over the gated files, printed whenever the count moves.
 * A difference between two machines is nearly always one rule, and without
 * this the only thing either side can compare is a single number.
 */
function summarise(results, cwd) {
  const byRule = {}
  let files = 0

  for (const result of results) {
    if (!isCounted(path.relative(cwd, result.filePath))) continue
    files++
    for (const message of result.messages) {
      const rule = message.ruleId || '(parse error)'
      byRule[rule] = (byRule[rule] || 0) + 1
    }
  }

  const lines = Object.entries(byRule)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 12)
    .map(([rule, n]) => `  ${String(n).padStart(5)}  ${rule}`)

  return [`files linted: ${files}`, ...lines].join('\n')
}

async function main() {
  const {ESLint} = require('eslint')
  const cwd = process.cwd()

  const results = await new ESLint({ignorePath: '.gitignore'}).lintFiles(
    LINT_TARGETS,
  )

  const outcome = evaluate({
    count: countProblems(results, cwd),
    baseline: readBaseline(),
  })

  console.log(outcome.message)
  if (!/unchanged/.test(outcome.message)) console.log(summarise(results, cwd))
  if (!outcome.ok) process.exitCode = 1
}

if (require.main === module) {
  main().catch((error) => {
    console.error(error)
    process.exitCode = 1
  })
}

module.exports = {
  BASELINE_FILE,
  countProblems,
  evaluate,
  isCounted,
  readBaseline,
}
