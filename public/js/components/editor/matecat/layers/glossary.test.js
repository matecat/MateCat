import {createSegmentState} from '../../core/createSegmentEditor'
import {
  findTerms,
  GLOSSARY_CLASS,
  glossaryRanges,
  QA_GLOSSARY_CLASS,
  qaCheckGlossaryRanges,
} from './glossary'

const term = (id, words, extra = {}) => ({
  term_id: id,
  matching_words: words,
  ...extra,
})

const marked = (state, ranges) =>
  ranges.map(({from, to, className, data}) => [
    state.sliceDoc(from, to),
    className,
    data,
  ])

beforeEach(() => {
  window.config = {...window.config, isCJK: false}
})

describe('glossaryRanges (activateGlossary)', () => {
  test('marks each term with its entry, for the footer click', () => {
    const state = createSegmentState('Book a guest room')
    expect(
      marked(
        state,
        glossaryRanges(state, {sid: '3', glossary: [term(11, ['guest'])]}),
      ),
    ).toEqual([
      ['guest', GLOSSARY_CLASS, {type: 'glossary', sid: '3', termId: 11}],
    ])
  })

  test('the longer of two overlapping terms wins (composite terms first)', () => {
    const state = createSegmentState('a Guest favorite place')
    const ranges = glossaryRanges(state, {
      sid: '3',
      glossary: [term(1, ['guest']), term(2, ['Guest favorite'])],
    })
    expect(
      marked(state, ranges).map(([text, , data]) => [text, data.termId]),
    ).toEqual([['Guest favorite', 2]])
  })

  test('only whole words match', () => {
    const state = createSegmentState('guesthouse')
    expect(
      glossaryRanges(state, {sid: '3', glossary: [term(1, ['guest'])]}),
    ).toEqual([])
  })

  test('blacklisted and missing terms are not glossary hits', () => {
    const state = createSegmentState('guest room')
    expect(
      glossaryRanges(state, {
        sid: '3',
        glossary: [
          term(1, ['guest'], {isBlacklist: true}),
          term(2, ['room'], {missingTerm: true}),
        ],
      }),
    ).toEqual([])
  })

  test('a term next to a tag is marked, the tag left out', () => {
    const state = createSegmentState('<g id="1">guest</g> room')
    expect(
      marked(
        state,
        glossaryRanges(state, {sid: '3', glossary: [term(1, ['guest'])]}),
      ).map((m) => m[0]),
    ).toEqual(['guest'])
  })

  test('no glossary, no ranges', () => {
    const state = createSegmentState('guest')
    expect(glossaryRanges(state, {sid: '3'})).toEqual([])
  })
})

describe('qaCheckGlossaryRanges (activateQaCheckGlossary)', () => {
  test('marks only the terms missing in the target', () => {
    const state = createSegmentState('guest room')
    const ranges = qaCheckGlossaryRanges(state, {
      sid: '3',
      glossary: [term(1, ['guest']), term(2, ['room'], {missingTerm: true})],
    })
    expect(marked(state, ranges)).toEqual([
      ['room', QA_GLOSSARY_CLASS, {type: 'check', sid: '3', termId: 2}],
    ])
  })

  test('nothing missing, nothing marked', () => {
    const state = createSegmentState('guest')
    expect(
      qaCheckGlossaryRanges(state, {sid: '3', glossary: [term(1, ['guest'])]}),
    ).toEqual([])
  })
})

describe('findTerms', () => {
  test('CJK text matches inside a run of characters', () => {
    window.config.isCJK = true
    expect(findTerms('我们的客房很好', [term(1, ['客房'])])).toEqual([
      {start: 3, end: 5, term: term(1, ['客房'])},
    ])
  })

  test('matching is case-insensitive and finds the entry whatever the case', () => {
    expect(findTerms('GUEST', [term(4, ['guest'])])[0].term.term_id).toBe(4)
  })

  test('entries without matching words find nothing', () => {
    expect(findTerms('guest', [{term_id: 1}])).toEqual([])
  })
})
