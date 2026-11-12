import SearchUtils from '../../../header/cattol/search/searchUtils'
import {createSegmentState} from '../../core/createSegmentEditor'
import {
  SEARCH_CHIP_CLASS,
  SEARCH_CHIP_CURRENT_CLASS,
  searchChipRanges,
  searchRanges,
} from './search'

// The search state SearchUtils keeps after a search: the featured result (index 1) is the second hit of segment 7.
const searchFor = (term, extra = {}) => {
  Object.assign(SearchUtils, {
    searchMode: 'normal',
    searchParams: {source: term, ...extra},
    occurrencesList: ['6', '7'],
    featuredSearchResult: 1,
    searchResultsDictionary: {
      7: {
        occurrences: [{searchProgressiveIndex: 0}, {searchProgressiveIndex: 1}],
      },
    },
  })
}

const marked = (state, segment) =>
  searchRanges(state, segment).map(({from, to, className, tagName}) => [
    state.sliceDoc(from, to),
    className,
    tagName,
  ])

describe('searchRanges (activateSearch)', () => {
  test('marks every hit, the featured one as the current item, in <mark> elements', () => {
    searchFor('save')
    const state = createSegmentState('Save <g id="1">and save</g>')
    expect(marked(state, {sid: '7', inSearch: true})).toEqual([
      ['Save', 'searchMarker', 'mark'],
      ['save', 'searchMarker currSearchItem', 'mark'],
    ])
  })

  test('a term never matches across a tag', () => {
    searchFor('ab')
    const state = createSegmentState('a<x id="1"/>b ab')
    expect(marked(state, {sid: '7', inSearch: true}).map((m) => m[0])).toEqual([
      'ab',
    ])
  })

  test('matches decoded text, not entities', () => {
    searchFor('A & B')
    const state = createSegmentState('x A &amp; B')
    expect(marked(state, {sid: '7', inSearch: true}).map((m) => m[0])).toEqual([
      'A & B',
    ])
  })

  test('a segment not in search, or not among the results, has no hits', () => {
    searchFor('save')
    const state = createSegmentState('save')
    expect(searchRanges(state, {sid: '7', inSearch: false})).toEqual([])
    expect(searchRanges(state, {sid: '8', inSearch: true})).toEqual([])
  })
})

describe('searchRanges on the target', () => {
  test('the target searches its own term, the source one is ignored', () => {
    Object.assign(SearchUtils, {
      searchMode: 'normal',
      searchParams: {target: 'casa'},
      occurrencesList: ['7'],
      featuredSearchResult: 0,
      searchResultsDictionary: {
        7: {occurrences: [{searchProgressiveIndex: 0}]},
      },
    })
    const state = createSegmentState('una casa')
    expect(
      searchRanges(state, {sid: '7', inSearch: true}, false).map(
        ({from, to, className}) => [state.sliceDoc(from, to), className],
      ),
    ).toEqual([['casa', 'searchMarker currSearchItem']])
    expect(searchRanges(state, {sid: '7', inSearch: true}, true)).toEqual([])
  })
})

describe('searchChipRanges (TagEntity markSearch)', () => {
  const ph = (equiv) =>
    `<ph id="mtc_1" ctype="x-html" equiv-text="base64:${btoa(equiv)}"/>`
  const segment = (params) => ({sid: '7', inSearch: true, searchParams: params})

  test('a chip whose label holds the searched text is marked, plain text is left to the search layer', () => {
    const state = createSegmentState('save ' + ph('%save%') + ' ' + ph('%s'))
    const chips = searchChipRanges(state, segment({source: 'SAVE'}))
    expect(chips).toHaveLength(1)
    expect(state.sliceDoc(chips[0].from, chips[0].to)).toContain('ph')
    expect(chips[0].className).toBe(SEARCH_CHIP_CLASS)
  })

  test('each side looks for its own term; match case and exact match are applied', () => {
    const state = createSegmentState(ph('Saved'))
    expect(searchChipRanges(state, segment({target: 'Saved'}), true)).toEqual(
      [],
    )
    expect(
      searchChipRanges(state, segment({target: 'Saved'}), false),
    ).toHaveLength(1)
    expect(
      searchChipRanges(state, segment({source: 'saved', ingnoreCase: true})),
    ).toEqual([])
    expect(
      searchChipRanges(state, segment({source: 'Save', exactMatch: true})),
    ).toEqual([])
  })

  test('nothing out of search', () => {
    const state = createSegmentState(ph('save'))
    expect(
      searchChipRanges(state, {...segment({source: 'save'}), inSearch: false}),
    ).toEqual([])
  })

  describe('a hit in a tag label takes its number among the hits of the segment', () => {
    // Segment 7 holds two hits of `name`: the first in the chip label, the second in the text.
    const featured = (index) =>
      Object.assign(SearchUtils, {
        searchMode: 'normal',
        searchParams: {source: 'name'},
        occurrencesList: ['7', '7'],
        featuredSearchResult: index,
        searchResultsDictionary: {
          7: {
            occurrences: [
              {searchProgressiveIndex: 0},
              {searchProgressiveIndex: 1},
            ],
          },
        },
      })
    const raw = () => 'a ' + ph('%name%') + ' name'

    test('the current hit after it is the text one', () => {
      featured(1)
      const state = createSegmentState(raw())
      expect(marked(state, segment({source: 'name'}))).toEqual([
        ['name', 'searchMarker currSearchItem', 'mark'],
      ])
      expect(
        searchChipRanges(state, segment({source: 'name'})).map(
          (c) => c.className,
        ),
      ).toEqual([SEARCH_CHIP_CLASS])
    })

    test('the current hit inside the label marks its chip as current', () => {
      featured(0)
      const state = createSegmentState(raw())
      expect(marked(state, segment({source: 'name'}))).toEqual([
        ['name', 'searchMarker', 'mark'],
      ])
      const chips = searchChipRanges(state, segment({source: 'name'}))
      expect(chips.map((c) => c.className)).toEqual([
        `${SEARCH_CHIP_CLASS} ${SEARCH_CHIP_CURRENT_CLASS}`,
      ])
      expect(state.sliceDoc(chips[0].from, chips[0].to)).toContain('ph')
    })
  })

  test('a term in the label of a compressible g is drawn on its chip but not counted, as the cattool does not count it', () => {
    // The cattool searches the g as its markup: segment 7 has one hit, the word after the tag
    Object.assign(SearchUtils, {
      searchMode: 'normal',
      searchParams: {source: 'name'},
      occurrencesList: ['7'],
      featuredSearchResult: 0,
      searchResultsDictionary: {
        7: {occurrences: [{searchProgressiveIndex: 0}]},
      },
    })
    const state = createSegmentState(
      `a <g id="1" equiv-text="base64:${btoa('%name%')}">b</g> name`,
    )
    expect(marked(state, segment({source: 'name'}))).toEqual([
      ['name', 'searchMarker currSearchItem', 'mark'],
    ])
    expect(
      searchChipRanges(state, segment({source: 'name'})).map(
        (c) => c.className,
      ),
    ).toEqual([SEARCH_CHIP_CLASS])
  })
})
