import {createSegmentState} from '../../core/createSegmentEditor'
import {BLACKLIST_CLASS, blacklistMessage, blacklistRanges} from './blacklist'

const term = (id, words, source, target) => ({
  term_id: id,
  matching_words: words,
  source: {term: source},
  target: {term: target},
})

beforeEach(() => {
  window.config = {...window.config, isCJK: false}
})

describe('blacklistRanges (activateQaCheckBlacklist)', () => {
  test('marks each blacklisted term of the target with its entry, for the tooltip', () => {
    const entry = term(4, ['cheap'], 'inexpensive', 'cheap')
    const state = createSegmentState('A cheap room')
    const ranges = blacklistRanges(state, {
      sid: '3',
      qaBlacklistGlossary: [entry],
    })
    expect(
      ranges.map(({from, to, className, data}) => [
        state.sliceDoc(from, to),
        className,
        data,
      ]),
    ).toEqual([
      ['cheap', BLACKLIST_CLASS, {type: 'blacklist', sid: '3', term: entry}],
    ])
  })

  test('a term is matched in the text around tags, never across one', () => {
    const state = createSegmentState('che<x id="1"/>ap and <g id="2">cheap</g>')
    const ranges = blacklistRanges(state, {
      sid: '3',
      qaBlacklistGlossary: [term(4, ['cheap'], '', 'cheap')],
    })
    expect(ranges.map(({from, to}) => state.sliceDoc(from, to))).toEqual([
      'cheap',
    ])
  })

  test('no QA check result yet, or nothing blacklisted: no marks', () => {
    const state = createSegmentState('A cheap room')
    expect(blacklistRanges(state, {sid: '3'})).toEqual([])
    expect(blacklistRanges(state, {sid: '3', qaBlacklistGlossary: []})).toEqual(
      [],
    )
  })
})

describe('blacklistMessage (QaCheckBlacklistHighlight)', () => {
  test('names the source term it is a forbidden translation for', () => {
    expect(blacklistMessage(term(1, [], 'inexpensive', 'cheap'))).toBe(
      'cheap is flagged as a forbidden translation for inexpensive',
    )
  })

  test('a term with no source is a forbidden word', () => {
    expect(blacklistMessage(term(1, [], '', 'cheap'))).toBe(
      'cheap is flagged as a forbidden word',
    )
  })
})
