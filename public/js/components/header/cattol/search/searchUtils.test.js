import SearchUtils from './searchUtils'
import SegmentStore from '../../../../stores/SegmentStore'
import CatToolActions from '../../../../actions/CatToolActions'
import ModalsActions from '../../../../actions/ModalsActions'
import {
  addSearchResultToSegments,
  removeSearchResultToSegments,
} from '../../../../actions/segmentDispatchActions'
import {searchTermIntoSegments} from '../../../../api/searchTermIntoSegments'
import {replaceAllIntoSegments} from '../../../../api/replaceAllIntoSegments'
import {REVISE_STEP_NUMBER} from '../../../../constants/Constants'

jest.mock('../../../../actions/CatToolActions')
jest.mock('../../../../actions/ModalsActions')
jest.mock('../../../../actions/segmentDispatchActions')
jest.mock('../../../../api/searchTermIntoSegments')
jest.mock('../../../../api/replaceAllIntoSegments')

const baseSearchFormParams = () => ({
  searchSource: '',
  searchTarget: '',
  selectStatus: 'all',
  replaceTarget: '',
  matchCase: false,
  exactMatch: false,
  entireJob: false,
})

beforeEach(() => {
  jest.clearAllMocks()
  SearchUtils.searchEnabled = true
  SearchUtils.searchOpen = false
  SearchUtils.searchParams = {search: 0}
  SearchUtils.total = 0
  SearchUtils.searchResults = []
  SearchUtils.occurrencesList = []
  SearchUtils.searchResultsDictionary = {}
  SearchUtils.featuredSearchResult = 0
  SearchUtils.searchSegmentsResult = []
  delete SearchUtils.searchMode
})

describe('execFind', () => {
  test('shows an alert and returns false when source, target and status are all empty', () => {
    const result = SearchUtils.execFind(baseSearchFormParams())

    expect(result).toBe(false)
    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      expect.any(Function),
      {
        text: 'Enter text in source or target input boxes or select a status.',
      },
      'Search Alert',
    )
    expect(searchTermIntoSegments).not.toHaveBeenCalled()
  })

  test('searches in normal mode when only source is provided', () => {
    searchTermIntoSegments.mockResolvedValue({segments: [], total: 0})

    SearchUtils.execFind({...baseSearchFormParams(), searchSource: 'hello'})

    expect(SearchUtils.searchMode).toBe('normal')
    expect(SearchUtils.searchOpen).toBe(true)
    expect(searchTermIntoSegments).toHaveBeenCalledWith(
      expect.objectContaining({source: 'hello', target: ''}),
    )
  })

  test('searches in source&target mode when both are provided', () => {
    searchTermIntoSegments.mockResolvedValue({segments: [], total: 0})

    SearchUtils.execFind({
      ...baseSearchFormParams(),
      searchSource: 'foo',
      searchTarget: 'bar',
    })

    expect(SearchUtils.searchMode).toBe('source&target')
  })

  test('maps APPROVED status with second revision to the approved2 status param', () => {
    searchTermIntoSegments.mockResolvedValue({segments: [], total: 0})

    SearchUtils.execFind({
      ...baseSearchFormParams(),
      searchSource: 'x',
      selectStatus: 'APPROVED',
      revisionNumber: REVISE_STEP_NUMBER.REVISE2,
    })

    expect(searchTermIntoSegments).toHaveBeenCalledWith(
      expect.objectContaining({status: 'approved2'}),
    )
  })

  test('passes through match case, exact match, entire job and replace params', () => {
    searchTermIntoSegments.mockResolvedValue({segments: [], total: 0})

    SearchUtils.execFind({
      ...baseSearchFormParams(),
      searchSource: 'x',
      replaceTarget: 'y',
      matchCase: true,
      exactMatch: true,
      entireJob: true,
    })

    expect(searchTermIntoSegments).toHaveBeenCalledWith(
      expect.objectContaining({
        matchcase: true,
        exactmatch: true,
        inCurrentChunkOnly: false,
        replace: 'y',
      }),
    )
  })

  test('calls execFind_success once the search request resolves', async () => {
    searchTermIntoSegments.mockResolvedValue({segments: [1], total: 1})
    jest.spyOn(SegmentStore, 'getSegmentByIdToJS').mockReturnValue({
      decodedSource: 'hello',
      decodedTranslation: 'ciao',
    })
    const successSpy = jest.spyOn(SearchUtils, 'execFind_success')

    SearchUtils.execFind({...baseSearchFormParams(), searchSource: 'hello'})
    await Promise.resolve()
    await Promise.resolve()

    expect(successSpy).toHaveBeenCalledWith({segments: [1], total: 1})
  })
})

describe('execFind_success', () => {
  test('stores search results and notifies segments when matches are found', () => {
    jest.spyOn(SegmentStore, 'getSegmentByIdToJS').mockReturnValue({
      decodedSource: 'hello world',
      decodedTranslation: 'ciao mondo',
    })
    SearchUtils.searchParams = {
      source: 'hello',
      'match-case': false,
      'exact-match': false,
      searchMode: 'normal',
    }

    SearchUtils.execFind_success({segments: [10], total: 1})

    expect(CatToolActions.storeSearchResults).toHaveBeenCalledWith(
      expect.objectContaining({total: 1, featuredSearchResult: 0}),
    )
    expect(addSearchResultToSegments).toHaveBeenCalled()
  })

  test('resets search and stores empty results when no matches are found', () => {
    SearchUtils.execFind_success({segments: [], total: 0})

    expect(removeSearchResultToSegments).toHaveBeenCalled()
    expect(CatToolActions.storeSearchResults).toHaveBeenCalledWith(
      expect.objectContaining({
        total: 0,
        searchResults: [],
        occurrencesList: [],
      }),
    )
  })
})

describe('updateSearchObjectAfterReplace', () => {
  test('uses the provided segment array as the new search source', () => {
    jest.spyOn(SegmentStore, 'getSegmentByIdToJS').mockReturnValue({
      decodedSource: 'a',
      decodedTranslation: 'b',
    })
    SearchUtils.searchParams = {
      source: 'a',
      'match-case': false,
      'exact-match': false,
      searchMode: 'normal',
    }

    const result = SearchUtils.updateSearchObjectAfterReplace([1, 2])

    expect(SearchUtils.searchSegmentsResult).toEqual([1, 2])
    expect(result.occurrencesList).toBeDefined()
  })

  test('falls back to the previous segment result when none is provided', () => {
    SearchUtils.searchSegmentsResult = [5]
    jest.spyOn(SegmentStore, 'getSegmentByIdToJS').mockReturnValue({
      decodedSource: 'a',
      decodedTranslation: 'b',
    })
    SearchUtils.searchParams = {
      source: 'a',
      'match-case': false,
      'exact-match': false,
      searchMode: 'normal',
    }

    SearchUtils.updateSearchObjectAfterReplace(null)

    expect(SearchUtils.searchSegmentsResult).toEqual([5])
  })
})

describe('updateSearchObject', () => {
  test('keeps the featured index when the segment is still present', () => {
    SearchUtils.occurrencesList = [1, 2, 3]
    SearchUtils.featuredSearchResult = 1
    SearchUtils.searchSegmentsResult = [1, 2, 3]
    SearchUtils.searchParams = {
      source: 'a',
      'match-case': false,
      'exact-match': false,
      searchMode: 'normal',
    }
    jest.spyOn(SegmentStore, 'getSegmentByIdToJS').mockReturnValue({
      decodedSource: 'a',
      decodedTranslation: 'b',
    })

    const result = SearchUtils.updateSearchObject()

    expect(result.featuredSearchResult).toBe(SearchUtils.featuredSearchResult)
  })

  test('advances the featured index when the current segment disappears', () => {
    SearchUtils.occurrencesList = [1, 2]
    SearchUtils.featuredSearchResult = 5
    SearchUtils.searchSegmentsResult = [10, 20]
    SearchUtils.searchParams = {
      source: 'a',
      'match-case': false,
      'exact-match': false,
      searchMode: 'normal',
    }
    jest.spyOn(SegmentStore, 'getSegmentByIdToJS').mockReturnValue({
      decodedSource: 'a',
      decodedTranslation: 'b',
    })

    SearchUtils.updateSearchObject()

    expect(SearchUtils.featuredSearchResult).toBe(6)
  })
})

describe('getSearchRegExp', () => {
  test('adds the case-insensitive flag when ignoreCase is false', () => {
    const reg = SearchUtils.getSearchRegExp('foo', false, false)
    expect(reg.flags).toContain('i')
    expect(reg.test('FOO')).toBe(true)
  })

  test('omits the case-insensitive flag when ignoreCase is true', () => {
    const reg = SearchUtils.getSearchRegExp('foo', true, false)
    expect(reg.flags).not.toContain('i')
  })

  test('builds a word-boundary regex for exact matches', () => {
    const reg = SearchUtils.getSearchRegExp('foo', false, true)
    expect(reg.source).toContain('\\b')
  })

  test('wraps the middle-dot marker in zero-width spaces when requested', () => {
    const reg = SearchUtils.getSearchRegExp('a·b', false, false, true)
    expect(reg.source).toContain('​·​')
  })

  test('leaves the middle-dot marker untouched when not requested', () => {
    const reg = SearchUtils.getSearchRegExp('a·b', false, false, false)
    expect(reg.source).not.toContain('​')
  })
})

describe('getMatchesInText', () => {
  test('returns every regex match found in the text', () => {
    const matches = Array.from(
      SearchUtils.getMatchesInText('foo bar foo', 'foo', false, false),
    )
    expect(matches.length).toBe(2)
  })
})

describe('createSearchObject', () => {
  test('picks the richer occurrence set in source&target mode', () => {
    jest.spyOn(SegmentStore, 'getSegmentByIdToJS').mockReturnValue({
      decodedSource: 'foo foo',
      decodedTranslation: 'bar',
    })
    SearchUtils.searchParams = {
      source: 'foo',
      target: 'bar',
      'match-case': false,
      'exact-match': false,
      searchMode: 'source&target',
    }

    const result = SearchUtils.createSearchObject([1])

    expect(result.occurrencesList).toEqual([1, 1])
    expect(result.searchResultsDictionary[1].occurrences.length).toBe(2)
  })

  test('finds occurrences in source only for normal mode', () => {
    jest.spyOn(SegmentStore, 'getSegmentByIdToJS').mockReturnValue({
      decodedSource: 'foo bar',
      decodedTranslation: 'baz',
    })
    SearchUtils.searchParams = {
      source: 'foo',
      target: undefined,
      'match-case': false,
      'exact-match': false,
      searchMode: 'normal',
    }

    const result = SearchUtils.createSearchObject([2])

    expect(result.occurrencesList).toEqual([2])
  })

  test('finds occurrences in target only for normal mode when only target is set', () => {
    jest.spyOn(SegmentStore, 'getSegmentByIdToJS').mockReturnValue({
      decodedSource: 'foo',
      decodedTranslation: 'bar bar',
    })
    SearchUtils.searchParams = {
      source: undefined,
      target: 'bar',
      'match-case': false,
      'exact-match': false,
      searchMode: 'normal',
    }

    const result = SearchUtils.createSearchObject([3])

    expect(result.occurrencesList).toEqual([3, 3])
  })

  test('pushes the sid directly when the segment cannot be found', () => {
    jest.spyOn(SegmentStore, 'getSegmentByIdToJS').mockReturnValue(undefined)
    SearchUtils.searchParams = {
      source: 'foo',
      target: undefined,
      'match-case': false,
      'exact-match': false,
      searchMode: 'normal',
    }

    const result = SearchUtils.createSearchObject([4])

    expect(result.occurrencesList).toEqual([4])
    expect(result.searchResultsDictionary[4].occurrences).toEqual([])
  })
})

describe('toggleSearch', () => {
  test('does nothing when search is disabled', () => {
    SearchUtils.searchEnabled = false

    SearchUtils.toggleSearch({preventDefault: jest.fn()})

    expect(CatToolActions.toggleSearch).not.toHaveBeenCalled()
    expect(CatToolActions.closeSearch).not.toHaveBeenCalled()
  })

  test('closes the search when it is already open', () => {
    SearchUtils.searchOpen = true

    SearchUtils.toggleSearch({preventDefault: jest.fn()})

    expect(CatToolActions.closeSearch).toHaveBeenCalled()
  })

  test('prevents default and opens the search when it is closed', () => {
    SearchUtils.searchOpen = false
    const event = {preventDefault: jest.fn()}

    SearchUtils.toggleSearch(event)

    expect(event.preventDefault).toHaveBeenCalled()
    expect(CatToolActions.toggleSearch).toHaveBeenCalled()
  })
})

describe('execReplaceAll', () => {
  test('shows an alert and returns false when the target is empty', () => {
    const result = SearchUtils.execReplaceAll({
      searchSource: 'x',
      searchTarget: '',
      replaceTarget: '',
      selectStatus: '',
      matchCase: false,
      exactMatch: false,
    })

    expect(result).toBe(false)
    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      expect.any(Function),
      {text: 'You must specify the Target value to replace.'},
      'Search Alert',
    )
  })

  test('shows an alert and returns false when the replace value is empty', () => {
    const result = SearchUtils.execReplaceAll({
      searchSource: 'x',
      searchTarget: 'y',
      replaceTarget: '',
      selectStatus: '',
      matchCase: false,
      exactMatch: false,
    })

    expect(result).toBe(false)
    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      expect.any(Function),
      {text: 'You must specify the replacement value.'},
      'Search Alert',
    )
  })

  test('calls replaceAllIntoSegments with the encoded search params', async () => {
    replaceAllIntoSegments.mockResolvedValue({})

    const result = SearchUtils.execReplaceAll({
      searchSource: 'foo',
      searchTarget: 'bar',
      replaceTarget: 'baz',
      selectStatus: 'NEW',
      matchCase: true,
      exactMatch: false,
    })

    expect(replaceAllIntoSegments).toHaveBeenCalledWith(
      expect.objectContaining({
        source: 'foo',
        target: 'bar',
        replace: 'baz',
        status: 'NEW',
        matchcase: true,
        exactmatch: false,
      }),
    )
    await result
  })

  test('clears the status param when the status filter is "all"', () => {
    replaceAllIntoSegments.mockResolvedValue({})

    SearchUtils.execReplaceAll({
      searchSource: '',
      searchTarget: 'bar',
      replaceTarget: 'baz',
      selectStatus: 'all',
      matchCase: false,
      exactMatch: false,
    })

    expect(replaceAllIntoSegments).toHaveBeenCalledWith(
      expect.objectContaining({status: undefined}),
    )
  })
})

describe('updateFeaturedResult', () => {
  test('stores the given value as the featured search result', () => {
    SearchUtils.updateFeaturedResult(7)
    expect(SearchUtils.featuredSearchResult).toBe(7)
  })
})

describe('prepareTextToReplace', () => {
  test('replaces angle brackets with placeholders and records their intervals', () => {
    const {text, tagsIntervals} = SearchUtils.prepareTextToReplace('a<b>c')

    expect(text).toBe('a##LESSTHAN##b##GREATERTHAN##c')
    expect(tagsIntervals.length).toBe(1)
    expect(tagsIntervals[0].start).toBeLessThan(tagsIntervals[0].end)
  })

  test('returns no intervals for plain text without tags', () => {
    const {text, tagsIntervals} = SearchUtils.prepareTextToReplace('plain text')

    expect(text).toBe('plain text')
    expect(tagsIntervals).toEqual([])
  })
})

describe('restoreTextAfterReplace', () => {
  test('converts placeholders back into angle brackets', () => {
    const result = SearchUtils.restoreTextAfterReplace(
      'a##LESSTHAN##b##GREATERTHAN##c',
    )
    expect(result).toBe('a<b>c')
  })
})

describe('markText', () => {
  test('returns the text unchanged when the sid is not part of the results', () => {
    SearchUtils.occurrencesList = [1, 2]
    const result = SearchUtils.markText('hello', true, 99)
    expect(result).toBe('hello')
  })

  test('wraps the current featured source match with the current-item class', () => {
    SearchUtils.occurrencesList = [5]
    SearchUtils.featuredSearchResult = 0
    SearchUtils.searchResultsDictionary = {
      5: {occurrences: [{searchProgressiveIndex: 0}]},
    }
    SearchUtils.searchParams = {
      source: 'foo',
      target: undefined,
      'match-case': false,
      'exact-match': false,
    }
    SearchUtils.searchMode = 'normal'

    const result = SearchUtils.markText('foo bar', true, 5)

    expect(result).toBe(
      '<mark class="searchMarker currSearchItem">foo</mark> bar',
    )
  })

  test('wraps a non-featured target match without the current-item class', () => {
    SearchUtils.occurrencesList = [5]
    SearchUtils.featuredSearchResult = 1
    SearchUtils.searchResultsDictionary = {
      5: {occurrences: [{searchProgressiveIndex: 0}]},
    }
    SearchUtils.searchParams = {
      source: undefined,
      target: 'bar',
      'match-case': false,
      'exact-match': false,
    }
    SearchUtils.searchMode = 'normal'

    const result = SearchUtils.markText('foo bar', false, 5)

    expect(result).toBe('foo <mark class="searchMarker">bar</mark>')
  })

  test('builds the regex from source or target when in source&target mode', () => {
    SearchUtils.occurrencesList = [5]
    SearchUtils.featuredSearchResult = 0
    SearchUtils.searchResultsDictionary = {
      5: {occurrences: [{searchProgressiveIndex: 0}]},
    }
    SearchUtils.searchParams = {
      source: 'foo',
      target: 'bar',
      'match-case': false,
      'exact-match': false,
    }
    SearchUtils.searchMode = 'source&target'

    const result = SearchUtils.markText('foo baz', true, 5)

    expect(result).toContain('<mark')
  })

  test('applies word-boundary matching for exact-match searches', () => {
    SearchUtils.occurrencesList = [5]
    SearchUtils.featuredSearchResult = 0
    SearchUtils.searchResultsDictionary = {
      5: {occurrences: [{searchProgressiveIndex: 0}]},
    }
    SearchUtils.searchParams = {
      source: 'foo',
      target: undefined,
      'match-case': false,
      'exact-match': true,
    }
    SearchUtils.searchMode = 'normal'

    const result = SearchUtils.markText('foofoo foo', true, 5)

    expect(result).toBe(
      'foofoo <mark class="searchMarker currSearchItem">foo</mark>',
    )
  })

  test('matches a double space using the nbsp fallback regex', () => {
    SearchUtils.occurrencesList = [5]
    SearchUtils.featuredSearchResult = 0
    SearchUtils.searchResultsDictionary = {
      5: {occurrences: [{searchProgressiveIndex: 0}]},
    }
    SearchUtils.searchParams = {
      source: '  ',
      target: undefined,
      'match-case': false,
      'exact-match': false,
    }
    SearchUtils.searchMode = 'normal'

    const result = SearchUtils.markText('a&nbsp; b', true, 5)

    expect(result).toContain('<mark')
  })

  test('skips wrapping matches that fall inside a tag placeholder', () => {
    SearchUtils.occurrencesList = [5]
    SearchUtils.featuredSearchResult = 0
    SearchUtils.searchResultsDictionary = {
      5: {occurrences: [{searchProgressiveIndex: 0}]},
    }
    SearchUtils.searchParams = {
      source: 'b',
      target: undefined,
      'match-case': false,
      'exact-match': false,
    }
    SearchUtils.searchMode = 'normal'

    const result = SearchUtils.markText('a<b>c', true, 5)

    expect(result).toBe('a<b>c')
    expect(result).not.toContain('<mark')
  })
})

describe('resetSearch', () => {
  test('clears all search state and removes results from segments', () => {
    SearchUtils.searchResults = [1]
    SearchUtils.occurrencesList = [1]
    SearchUtils.searchResultsDictionary = {1: {}}
    SearchUtils.featuredSearchResult = 3
    SearchUtils.searchSegmentsResult = [1]

    SearchUtils.resetSearch()

    expect(SearchUtils.searchResults).toEqual([])
    expect(SearchUtils.occurrencesList).toEqual([])
    expect(SearchUtils.searchResultsDictionary).toEqual({})
    expect(SearchUtils.featuredSearchResult).toBe(0)
    expect(SearchUtils.searchSegmentsResult).toEqual([])
    expect(removeSearchResultToSegments).toHaveBeenCalled()
  })
})

describe('closeSearch', () => {
  test('resets state and notifies the store with empty results', () => {
    SearchUtils.closeSearch()

    expect(CatToolActions.closeSubHeader).toHaveBeenCalled()
    expect(removeSearchResultToSegments).toHaveBeenCalled()
    expect(CatToolActions.storeSearchResults).toHaveBeenCalledWith(
      expect.objectContaining({
        total: 0,
        searchResults: [],
        occurrencesList: [],
      }),
    )
  })
})
