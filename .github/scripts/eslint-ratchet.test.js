'use strict'

const assert = require('node:assert')
const path = require('node:path')
const {test} = require('node:test')

const {
  addedViolations,
  evaluate,
  formatAdded,
  indexViolations,
  isCounted,
  totalOf,
} = require('./eslint-ratchet.js')

const cwd = path.sep === '/' ? '/repo' : 'C:\\repo'
const key = (file, rule, message) => [file, rule, message].join('\u0000')

const entry = (over = {}) => ({
  file: 'public/js/a.js',
  rule: 'no-unused-vars',
  message: "'x' is assigned a value but never used.",
  count: 1,
  locations: [{line: 4, column: 7}],
  by: 1,
  ...over,
})

test('a failing run names file, line, rule and message', () => {
  const outcome = evaluate({base: 10, head: 11, added: [entry()]})

  assert.equal(outcome.ok, false)
  assert.match(
    outcome.message,
    /public\/js\/a\.js:4:7 {2}no-unused-vars {2}'x' is assigned/,
  )
})

test('added violations fail even when the total falls', () => {
  const outcome = evaluate({base: 300, head: 100, added: [entry()]})

  assert.equal(outcome.ok, false)
  assert.match(outcome.message, /fell from 300 to 100, but this branch adds/)
})

test('a rising total says so in the headline', () => {
  const outcome = evaluate({base: 10, head: 12, added: [entry({by: 2})]})

  assert.match(outcome.message, /rose from 10 to 12 \(\+2\)/)
})

test('a long report is truncated', () => {
  const added = Array.from({length: 40}, (_, i) =>
    entry({file: `f${i}.js`, locations: [{line: 1, column: 1}]}),
  )

  const outcome = evaluate({base: 1, head: 41, added})

  assert.match(outcome.message, /\.\.\.and 10 more/)
})

test('an unchanged total passes', () => {
  const outcome = evaluate({base: 10, head: 10})

  assert.equal(outcome.ok, true)
  assert.match(outcome.message, /unchanged at 10/)
})

test('a drop with nothing added passes', () => {
  const outcome = evaluate({base: 10, head: 7})

  assert.equal(outcome.ok, true)
  assert.match(outcome.message, /fell from 10 to 7 \(-3\)/)
})

test('reaching zero passes', () => {
  assert.equal(evaluate({base: 0, head: 0}).ok, true)
})

test('plugins/ is outside the gated set', () => {
  assert.equal(isCounted('public/js/index.js'), true)
  assert.equal(isCounted('plugins/aligner/app/src/App.js'), false)
  // A path that merely starts with the same letters is still counted.
  assert.equal(isCounted('pluginsomething/app.js'), true)
})

test('indexViolations keys by file, rule and message, skipping plugins/', () => {
  const index = indexViolations(
    [
      {
        filePath: path.join(cwd, 'public/js/a.js'),
        messages: [
          {ruleId: 'no-undef', message: "'a' is not defined.", line: 1, column: 1},
          {ruleId: 'no-undef', message: "'a' is not defined.", line: 9, column: 3},
          {ruleId: 'no-undef', message: "'b' is not defined.", line: 4, column: 2},
          {ruleId: null, message: 'Parsing error', line: 1, column: 1},
        ],
      },
      {
        filePath: path.join(cwd, 'plugins/aligner/b.js'),
        messages: [{ruleId: 'no-undef', message: 'x', line: 1, column: 1}],
      },
    ],
    cwd,
  )

  assert.equal(Object.keys(index).length, 3)
  assert.equal(index[key('public/js/a.js', 'no-undef', "'a' is not defined.")].count, 2)
  assert.deepEqual(
    index[key('public/js/a.js', 'no-undef', "'a' is not defined.")].locations,
    [
      {line: 1, column: 1},
      {line: 9, column: 3},
    ],
  )
  assert.ok(index[key('public/js/a.js', '(parse error)', 'Parsing error')])
})

test('totalOf sums the counts, not the keys', () => {
  assert.equal(totalOf({a: {count: 2}, b: {count: 3}}), 5)
  assert.equal(totalOf({}), 0)
})

test('addedViolations reports only growth, and the newest locations', () => {
  const k = key('a.js', 'no-undef', "'a' is not defined.")

  const added = addedViolations(
    {[k]: {count: 1}},
    {
      [k]: {
        file: 'a.js',
        rule: 'no-undef',
        message: "'a' is not defined.",
        count: 3,
        locations: [
          {line: 1, column: 1},
          {line: 5, column: 1},
          {line: 9, column: 1},
        ],
      },
    },
  )

  assert.equal(added.length, 1)
  assert.equal(added[0].by, 2)
  // Only the two newest locations, not the pre-existing one.
  assert.deepEqual(added[0].locations, [
    {line: 5, column: 1},
    {line: 9, column: 1},
  ])
})

test('a violation absent from the base counts as all-new', () => {
  const k = key('new.js', 'no-undef', "'a' is not defined.")

  const added = addedViolations(
    {},
    {
      [k]: {
        file: 'new.js',
        rule: 'no-undef',
        message: "'a' is not defined.",
        count: 1,
        locations: [{line: 2, column: 4}],
      },
    },
  )

  assert.deepEqual(added[0].locations, [{line: 2, column: 4}])
})

test('a file that improves is not reported', () => {
  const k = key('a.js', 'no-undef', "'a' is not defined.")

  const added = addedViolations(
    {[k]: {count: 9}},
    {[k]: {file: 'a.js', rule: 'no-undef', message: 'm', count: 1, locations: []}},
  )

  assert.deepEqual(added, [])
})

test('formatAdded emits one line per location', () => {
  const lines = formatAdded([
    entry({
      by: 2,
      locations: [
        {line: 3, column: 1},
        {line: 8, column: 2},
      ],
    }),
  ])

  assert.equal(lines.length, 2)
  assert.match(lines[0], /a\.js:3:1/)
  assert.match(lines[1], /a\.js:8:2/)
})
