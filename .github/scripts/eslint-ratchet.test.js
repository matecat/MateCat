'use strict'

const assert = require('node:assert')
const fs = require('node:fs')
const os = require('node:os')
const path = require('node:path')
const {test} = require('node:test')

const {
  countProblems,
  evaluate,
  isCounted,
  readBaseline,
} = require('./eslint-ratchet.js')

test('a rise fails the gate and reports the delta', () => {
  const outcome = evaluate({count: 12, baseline: 10})

  assert.equal(outcome.ok, false)
  assert.match(outcome.message, /rose from 10 to 12 \(\+2\)/)
})

test('an unchanged total passes', () => {
  const outcome = evaluate({count: 10, baseline: 10})

  assert.equal(outcome.ok, true)
  assert.match(outcome.message, /unchanged at 10/)
})

test('a drop passes and names the number to write back', () => {
  const outcome = evaluate({count: 7, baseline: 10})

  assert.equal(outcome.ok, true)
  assert.match(outcome.message, /fell from 10 to 7 \(-3\)/)
  assert.match(outcome.message, /Set maxProblems to 7/)
})

test('reaching zero passes', () => {
  assert.equal(evaluate({count: 0, baseline: 0}).ok, true)
})

test('plugins/ is outside the gated set', () => {
  assert.equal(isCounted('public/js/index.js'), true)
  assert.equal(isCounted('plugins/aligner/app/src/App.js'), false)
  // A path that merely starts with the same letters is still counted.
  assert.equal(isCounted('pluginsomething/app.js'), true)
})

test('countProblems sums errors and warnings, skipping plugins/', () => {
  const cwd = '/repo'
  const results = [
    {filePath: '/repo/public/js/a.js', errorCount: 2, warningCount: 3},
    {filePath: '/repo/plugins/aligner/b.js', errorCount: 9, warningCount: 9},
    {filePath: '/repo/lib/c.js', errorCount: 0, warningCount: 1},
  ]

  assert.equal(countProblems(results, cwd), 6)
})

test('readBaseline rejects a malformed maxProblems', () => {
  const file = path.join(
    fs.mkdtempSync(path.join(os.tmpdir(), 'eslint-ratchet-')),
    'baseline.json',
  )

  fs.writeFileSync(file, JSON.stringify({maxProblems: -1}))
  assert.throws(() => readBaseline(file), /non-negative integer/)

  fs.writeFileSync(file, JSON.stringify({maxProblems: '10'}))
  assert.throws(() => readBaseline(file), /non-negative integer/)
})

test('readBaseline returns the committed number', () => {
  assert.equal(Number.isInteger(readBaseline()), true)
})
