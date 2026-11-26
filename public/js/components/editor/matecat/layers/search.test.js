import SearchUtils from '../../../header/cattol/search/searchUtils'
import {createSegmentState} from '../../core/createSegmentEditor'
import {searchRanges} from './search'

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
