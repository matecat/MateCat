window.config = {lxq_partnerid: 'test'}

import LXQ from './lxq.main'

const range = (overrides = {}) => ({
  myClass: '',
  errorid: '',
  suggestions: ['fixed word'],
  start: 3,
  end: 8,
  ...overrides,
})

beforeEach(() => {
  LXQ.lexiqaData = {lexiqaWarnings: {}}
})

test('does not add suggestion messages when building tooltip for the source segment', () => {
  const messages = LXQ.buildTooltipMessages(range(), 1, true)

  expect(messages).toEqual([])
})

test('adds a suggestion message for each suggestion when building tooltip for the target', () => {
  const messages = LXQ.buildTooltipMessages(range(), 1, false)

  expect(messages).toEqual([
    {msg: 'fixed word', start: 3, end: 8, type: 'suggestion'},
  ])
})

test('adds one suggestion message per suggestion, sharing the warning offsets', () => {
  const messages = LXQ.buildTooltipMessages(
    range({suggestions: ['first', 'second']}),
    1,
    false,
  )

  expect(messages).toEqual([
    {msg: 'first', start: 3, end: 8, type: 'suggestion'},
    {msg: 'second', start: 3, end: 8, type: 'suggestion'},
  ])
})

test('does not add anything when there are no suggestions', () => {
  const messages = LXQ.buildTooltipMessages(range({suggestions: []}), 1, false)

  expect(messages).toEqual([])
})
