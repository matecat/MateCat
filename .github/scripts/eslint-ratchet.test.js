'use strict'

const assert = require('node:assert')
const path = require('node:path')
const {test} = require('node:test')

const {
  countByFileRule,
  evaluate,
  isCounted,
  risenEntries,
  totalOf,
} = require('./eslint-ratchet.js')

const cwd = path.sep === '/' ? '/repo' : 'C:\\repo'
const key = (file, rule) => `${file}\u0000${rule}`

test('a rise against the base fails the gate', () => {
  const outcome = evaluate({
    base: 10,
    head: 12,
    risen: [{file: 'public/js/a.js', rule: 'no-undef', by: 2}],
  })

  assert.equal(outcome.ok, false)
  assert.match(outcome.message, /total rose from 10 to 12 \(\+2\)/)
  assert.match(outcome.message, /\+2 {2}no-undef {2}public\/js\/a\.js/)
})

test('a file that gains violations fails even when the total falls', () => {
  const outcome = evaluate({
    base: 300,
    head: 100,
    risen: [{file: 'public/js/new.js', rule: 'no-unused-vars', by: 2}],
  })

  assert.equal(outcome.ok, false)
  assert.match(outcome.message, /fell from 300 to 100, but these gained/)
  assert.match(outcome.message, /\+2 {2}no-unused-vars {2}public\/js\/new\.js/)
})

test('a long list of regressions is truncated', () => {
  const risen = Array.from({length: 25}, (_, i) => ({
    file: `f${i}.js`,
    rule: 'no-undef',
    by: 1,
  }))

  const outcome = evaluate({base: 1, head: 26, risen})

  assert.match(outcome.message, /\.\.\.and 5 more/)
})

test('an unchanged total passes', () => {
  const outcome = evaluate({base: 10, head: 10})

  assert.equal(outcome.ok, true)
  assert.match(outcome.message, /unchanged at 10/)
})

test('a drop with no regression passes', () => {
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

test('countByFileRule keys by file and rule, skipping plugins/', () => {
  const counts = countByFileRule(
    [
      {
        filePath: path.join(cwd, 'public/js/a.js'),
        messages: [
          {ruleId: 'no-undef'},
          {ruleId: 'no-undef'},
          {ruleId: null}, // a parse error carries no rule
        ],
      },
      {
        filePath: path.join(cwd, 'plugins/aligner/b.js'),
        messages: [{ruleId: 'no-undef'}],
      },
    ],
    cwd,
  )

  assert.deepEqual(counts, {
    [key('public/js/a.js', 'no-undef')]: 2,
    [key('public/js/a.js', '(parse error)')]: 1,
  })
})

test('totalOf sums every entry', () => {
  assert.equal(totalOf({a: 2, b: 3}), 5)
  assert.equal(totalOf({}), 0)
})

test('risenEntries reports growth per file, largest first', () => {
  const risen = risenEntries(
    {
      [key('a.js', 'no-undef')]: 5,
      [key('b.js', 'no-undef')]: 2,
      [key('c.js', 'no-undef')]: 1,
    },
    {
      [key('a.js', 'no-undef')]: 6,
      [key('b.js', 'no-undef')]: 5,
      [key('c.js', 'no-undef')]: 1,
    },
  )

  assert.deepEqual(risen, [
    {file: 'b.js', rule: 'no-undef', by: 3},
    {file: 'a.js', rule: 'no-undef', by: 1},
  ])
})

test('a file absent from the base counts as all-new', () => {
  const risen = risenEntries({}, {[key('new.js', 'no-undef')]: 4})

  assert.deepEqual(risen, [{file: 'new.js', rule: 'no-undef', by: 4}])
})

test('a file that improves is not reported', () => {
  const risen = risenEntries(
    {[key('a.js', 'no-undef')]: 9},
    {[key('a.js', 'no-undef')]: 1},
  )

  assert.deepEqual(risen, [])
})
