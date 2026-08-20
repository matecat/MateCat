import $ from 'jquery'
import SegmentFilterUtils from './segment_filter'
import SegmentStore from '../../../../stores/SegmentStore'
import SegmentActions from '../../../../actions/SegmentActions'
import CatToolActions from '../../../../actions/CatToolActions'
import CommonUtils from '../../../../utils/commonUtils'
import {getFilteredSegments} from '../../../../api/getFilteredSegments'
import {segmentTranslation} from '../../../../setTranslationUtil'

jest.mock('jquery', () => {
  const documentObj = {trigger: jest.fn()}
  const $ = jest.fn(() => documentObj)
  $.extend = (target, ...sources) => Object.assign(target, ...sources)
  return $
})

jest.mock('../../../../stores/SegmentStore', () => ({
  getSegmentByIdToJS: jest.fn(),
  getCurrentSegmentId: jest.fn(),
  getCurrentSegment: jest.fn(),
}))

jest.mock('../../../../actions/SegmentActions', () => ({
  removeAllMutedSegments: jest.fn(),
  setMutedSegments: jest.fn(),
  scrollToSegment: jest.fn(),
  openSegment: jest.fn(),
}))

jest.mock('../../../../actions/CatToolActions', () => ({
  addNotification: jest.fn(),
  setSegmentFilter: jest.fn(),
  setSegmentFilterError: jest.fn(),
  openSegmentFilter: jest.fn(),
  closeSubHeader: jest.fn(),
}))

jest.mock('../../../../utils/commonUtils', () => ({
  clearStorage: jest.fn(),
}))

jest.mock('../../../../api/getFilteredSegments', () => ({
  getFilteredSegments: jest.fn(),
}))

jest.mock('../../../../setTranslationUtil', () => ({
  segmentTranslation: jest.fn((segment, status, cb) => cb && cb()),
}))

beforeEach(() => {
  jest.clearAllMocks()
  localStorage.clear()
  global.config = {
    id_job: 42,
    password: 'pwd',
    review_password: 'revpwd',
    isReview: false,
    segmentFilterEnabled: true,
  }
  SegmentFilterUtils.cachedStoredState = null
  SegmentFilterUtils.open = false
  SegmentFilterUtils.filteringSegments = false
})

afterEach(() => {
  jest.useRealTimers()
})

describe('enabled', () => {
  test('reflects config.segmentFilterEnabled', () => {
    config.segmentFilterEnabled = true
    expect(SegmentFilterUtils.enabled()).toBe(true)
    config.segmentFilterEnabled = false
    expect(SegmentFilterUtils.enabled()).toBe(false)
  })
})

describe('keyForLocalStorage', () => {
  test('uses the translate page when not in review', () => {
    config.isReview = false
    expect(SegmentFilterUtils.keyForLocalStorage()).toBe(
      'SegmentFilter-v2-translate-42-pwd',
    )
  })

  test('uses the revise page when in review', () => {
    config.isReview = true
    expect(SegmentFilterUtils.keyForLocalStorage()).toBe(
      'SegmentFilter-v2-revise-42-pwd',
    )
  })
})

describe('segmentIsInSample', () => {
  test('returns true when the segment id is present', () => {
    expect(SegmentFilterUtils.segmentIsInSample(2, [1, 2, 3])).toBe(true)
  })

  test('returns false when the segment id is absent', () => {
    expect(SegmentFilterUtils.segmentIsInSample(9, [1, 2, 3])).toBe(false)
  })
})

describe('callbackForSegmentNotInSample', () => {
  test('adds a warning notification for the segment', () => {
    SegmentFilterUtils.callbackForSegmentNotInSample(123)

    expect(CatToolActions.addNotification).toHaveBeenCalledWith({
      uid: 'segment-filter',
      autoDismiss: false,
      dismissable: true,
      position: 'bl',
      text: 'Sample is trying to focus on segment #123, but segment is no longer in the sample',
      title: 'Segment not in sample',
      type: 'warning',
      allowHtml: true,
    })
  })
})

describe('getStoredState / setStoredState / clearStoredData', () => {
  test('returns the default shape when nothing is stored', () => {
    expect(SegmentFilterUtils.getStoredState()).toEqual({
      reactState: null,
      serverData: null,
      lastSegmentId: null,
    })
  })

  test('caches the result and does not re-read localStorage', () => {
    const getItemSpy = jest.spyOn(Storage.prototype, 'getItem')

    SegmentFilterUtils.getStoredState()
    SegmentFilterUtils.getStoredState()

    expect(getItemSpy).toHaveBeenCalledTimes(1)
    getItemSpy.mockRestore()
  })

  test('parses previously stored data from localStorage', () => {
    localStorage.setItem(
      SegmentFilterUtils.keyForLocalStorage(),
      JSON.stringify({
        reactState: {foo: 1},
        serverData: null,
        lastSegmentId: null,
      }),
    )

    expect(SegmentFilterUtils.getStoredState()).toEqual({
      reactState: {foo: 1},
      serverData: null,
      lastSegmentId: null,
    })
  })

  test('clears storage and returns null when stored data is corrupted', () => {
    const errorSpy = jest.spyOn(console, 'error').mockImplementation(() => {})
    localStorage.setItem(
      SegmentFilterUtils.keyForLocalStorage(),
      '{not valid json',
    )

    expect(SegmentFilterUtils.getStoredState()).toBeNull()
    expect(
      localStorage.getItem(SegmentFilterUtils.keyForLocalStorage()),
    ).toBeNull()
    expect(errorSpy).toHaveBeenCalled()

    errorSpy.mockRestore()
  })

  test('merges data into the current state and persists it', () => {
    SegmentFilterUtils.setStoredState({open: true})

    expect(SegmentFilterUtils.getStoredState()).toEqual({
      reactState: null,
      serverData: null,
      lastSegmentId: null,
      open: true,
    })
    const stored = JSON.parse(
      localStorage.getItem(SegmentFilterUtils.keyForLocalStorage()),
    )
    expect(stored.open).toBe(true)
  })

  test('clearStoredData resets the cache and removes the localStorage entry', () => {
    SegmentFilterUtils.setStoredState({open: true})

    SegmentFilterUtils.clearStoredData()

    expect(
      localStorage.getItem(SegmentFilterUtils.keyForLocalStorage()),
    ).toBeNull()
    expect(SegmentFilterUtils.getStoredState()).toEqual({
      reactState: null,
      serverData: null,
      lastSegmentId: null,
    })
  })
})

describe('filtering', () => {
  test('is true only when actively filtering and open', () => {
    SegmentFilterUtils.filteringSegments = true
    SegmentFilterUtils.open = true
    expect(SegmentFilterUtils.filtering()).toBe(true)
  })

  test('is false when not open', () => {
    SegmentFilterUtils.filteringSegments = true
    SegmentFilterUtils.open = false
    expect(SegmentFilterUtils.filtering()).toBe(false)
  })

  test('is false when not filtering', () => {
    SegmentFilterUtils.filteringSegments = false
    SegmentFilterUtils.open = true
    expect(SegmentFilterUtils.filtering()).toBe(false)
  })
})

describe('getLastFilterData', () => {
  test('returns the stored serverData', () => {
    SegmentFilterUtils.setStoredState({serverData: {segment_ids: [1, 2]}})
    expect(SegmentFilterUtils.getLastFilterData()).toEqual({
      segment_ids: [1, 2],
    })
  })
})

describe('tryToFocusLastSegment', () => {
  test('does nothing when there is no stored lastSegmentId', () => {
    SegmentFilterUtils.tryToFocusLastSegment()

    expect(SegmentActions.openSegment).not.toHaveBeenCalled()
    expect(SegmentActions.scrollToSegment).not.toHaveBeenCalled()
  })

  test('does nothing when the stored segment cannot be found', () => {
    SegmentFilterUtils.setStoredState({lastSegmentId: 5})
    SegmentStore.getSegmentByIdToJS.mockReturnValue(undefined)

    SegmentFilterUtils.tryToFocusLastSegment()

    expect(SegmentActions.openSegment).not.toHaveBeenCalled()
    expect(SegmentActions.scrollToSegment).not.toHaveBeenCalled()
  })

  test('scrolls to the segment when it is already open', () => {
    SegmentFilterUtils.setStoredState({lastSegmentId: 5})
    SegmentStore.getSegmentByIdToJS.mockReturnValue({
      opened: true,
      original_sid: 'orig-5',
      sid: 5,
    })

    SegmentFilterUtils.tryToFocusLastSegment()

    expect(SegmentActions.scrollToSegment).toHaveBeenCalledWith('orig-5')
    expect(SegmentActions.openSegment).not.toHaveBeenCalled()
  })

  test('opens the segment when it is not open', () => {
    SegmentFilterUtils.setStoredState({lastSegmentId: 5})
    SegmentStore.getSegmentByIdToJS.mockReturnValue({
      opened: false,
      original_sid: 'orig-5',
      sid: 5,
    })

    SegmentFilterUtils.tryToFocusLastSegment()

    expect(SegmentActions.openSegment).toHaveBeenCalledWith(5)
    expect(SegmentActions.scrollToSegment).not.toHaveBeenCalled()
  })
})

describe('initEvents', () => {
  test('does not register a segmentsAdded listener when the filter is disabled', () => {
    config.segmentFilterEnabled = false
    const addSpy = jest
      .spyOn(document, 'addEventListener')
      .mockImplementation(() => {})

    SegmentFilterUtils.initEvents()

    expect(addSpy).not.toHaveBeenCalled()
    addSpy.mockRestore()
  })

  test('focuses the last segment on segmentsAdded when actively filtering', () => {
    config.segmentFilterEnabled = true
    const addSpy = jest
      .spyOn(document, 'addEventListener')
      .mockImplementation(() => {})
    const focusSpy = jest
      .spyOn(SegmentFilterUtils, 'tryToFocusLastSegment')
      .mockImplementation(() => {})

    SegmentFilterUtils.initEvents()
    const [, handler] = addSpy.mock.calls[0]
    SegmentFilterUtils.filteringSegments = true
    SegmentFilterUtils.open = true
    handler()

    expect(focusSpy).toHaveBeenCalled()
    addSpy.mockRestore()
    focusSpy.mockRestore()
  })

  test('does not focus when not actively filtering', () => {
    config.segmentFilterEnabled = true
    const addSpy = jest
      .spyOn(document, 'addEventListener')
      .mockImplementation(() => {})
    const focusSpy = jest
      .spyOn(SegmentFilterUtils, 'tryToFocusLastSegment')
      .mockImplementation(() => {})

    SegmentFilterUtils.initEvents()
    const [, handler] = addSpy.mock.calls[0]
    SegmentFilterUtils.filteringSegments = false
    handler()

    expect(focusSpy).not.toHaveBeenCalled()
    addSpy.mockRestore()
    focusSpy.mockRestore()
  })
})

describe('filterSubmit', () => {
  test('submits the filter with the review password and opens the first segment', async () => {
    config.isReview = true
    config.currentPassword = 'revpwd'
    getFilteredSegments.mockResolvedValue({count: 2, segment_ids: ['10', '20']})

    SegmentFilterUtils.filterSubmit({status: 'TRANSLATED'})
    await Promise.resolve()
    await Promise.resolve()

    expect(getFilteredSegments).toHaveBeenCalledWith(
      42,
      'revpwd',
      expect.objectContaining({status: 'TRANSLATED', revision: true}),
    )
    expect(CommonUtils.clearStorage).toHaveBeenCalledWith('SegmentFilter')
    expect(SegmentActions.removeAllMutedSegments).toHaveBeenCalled()
    expect($()).toHaveProperty('trigger')
    expect($().trigger).toHaveBeenCalledWith(
      'segment-filter:filter-data:load',
      {
        data: {count: 2, segment_ids: ['10', '20']},
      },
    )
    expect(CatToolActions.setSegmentFilter).toHaveBeenCalledWith({
      count: 2,
      segment_ids: ['10', '20'],
    })
    expect(SegmentActions.setMutedSegments).toHaveBeenCalledWith(['10', '20'])
    expect(SegmentActions.scrollToSegment).toHaveBeenCalledWith('10')
    expect(SegmentActions.openSegment).toHaveBeenCalledWith('10')
    expect(SegmentFilterUtils.filteringSegments).toBe(true)
  })

  test('uses the standard password when not in review', async () => {
    config.isReview = false
    config.currentPassword = 'pwd'
    getFilteredSegments.mockResolvedValue({count: 0, segment_ids: []})

    SegmentFilterUtils.filterSubmit({status: 'NEW'})
    await Promise.resolve()
    await Promise.resolve()

    expect(getFilteredSegments).toHaveBeenCalledWith(
      42,
      'pwd',
      expect.objectContaining({status: 'NEW', revision: false}),
    )
  })

  test('defaults extended local storage values to an empty object', async () => {
    getFilteredSegments.mockResolvedValue({count: 0, segment_ids: []})

    SegmentFilterUtils.filterSubmit({status: 'NEW'})
    await Promise.resolve()
    await Promise.resolve()

    expect(SegmentFilterUtils.getStoredState().reactState).toEqual({
      filteredCount: 0,
      filtering: true,
      segmentsArray: [],
    })
  })

  test('notifies about a segment no longer in the sample when it is missing from the results', async () => {
    SegmentFilterUtils.setStoredState({lastSegmentId: 999})
    getFilteredSegments.mockResolvedValue({count: 2, segment_ids: ['1', '2']})

    SegmentFilterUtils.filterSubmit({status: 'NEW'})
    await Promise.resolve()
    await Promise.resolve()

    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Segment not in sample'}),
    )
    expect(SegmentActions.openSegment).not.toHaveBeenCalledWith(999)
  })

  test('re-opens the last focused segment when it is still in the sample', async () => {
    SegmentFilterUtils.setStoredState({lastSegmentId: '2'})
    getFilteredSegments.mockResolvedValue({count: 2, segment_ids: ['1', '2']})

    SegmentFilterUtils.filterSubmit({status: 'NEW'})
    await Promise.resolve()
    await Promise.resolve()

    expect(SegmentActions.openSegment).toHaveBeenCalledWith('2')
    expect(SegmentActions.scrollToSegment).toHaveBeenCalledWith('2')
  })

  test('notifies and flags the error on failure', async () => {
    getFilteredSegments.mockRejectedValue(new Error('boom'))

    SegmentFilterUtils.filterSubmit({status: 'NEW'})
    await Promise.resolve()
    await Promise.resolve()

    expect(CatToolActions.setSegmentFilterError).toHaveBeenCalled()
    expect(CatToolActions.addNotification).toHaveBeenCalledWith({
      title: 'Segments filters error',
      type: 'error',
      text: 'We got an error, please contact support',
      position: 'br',
      timer: 5000,
    })
  })
})

describe('openFilter', () => {
  test('opens the panel and stores the open flag without existing server data', () => {
    SegmentFilterUtils.openFilter()

    expect(CatToolActions.openSegmentFilter).toHaveBeenCalledTimes(1)
    expect(SegmentFilterUtils.open).toBe(true)
    expect(SegmentFilterUtils.getStoredState().open).toBe(true)
    expect(SegmentActions.setMutedSegments).not.toHaveBeenCalled()
  })

  test('replays the last filtered data after 200ms when server data was cached', () => {
    jest.useFakeTimers()
    SegmentFilterUtils.setStoredState({
      serverData: {segment_ids: ['1', '2']},
      reactState: {filteredCount: 2},
    })
    const focusSpy = jest
      .spyOn(SegmentFilterUtils, 'tryToFocusLastSegment')
      .mockImplementation(() => {})

    SegmentFilterUtils.openFilter()

    expect(SegmentActions.setMutedSegments).toHaveBeenCalledWith(['1', '2'])
    expect(SegmentFilterUtils.filteringSegments).toBe(true)

    jest.advanceTimersByTime(200)

    expect(CatToolActions.setSegmentFilter).toHaveBeenCalledWith(
      {segment_ids: ['1', '2']},
      {filteredCount: 2},
    )
    expect(CatToolActions.openSegmentFilter).toHaveBeenCalledTimes(2)
    expect(focusSpy).toHaveBeenCalled()

    focusSpy.mockRestore()
  })
})

describe('clearFilter', () => {
  test('clears stored data, stops filtering and unmutes all segments', () => {
    SegmentFilterUtils.setStoredState({open: true})
    SegmentFilterUtils.filteringSegments = true

    SegmentFilterUtils.clearFilter()

    expect(SegmentFilterUtils.filteringSegments).toBe(false)
    expect(SegmentActions.removeAllMutedSegments).toHaveBeenCalled()
    expect(
      localStorage.getItem(SegmentFilterUtils.keyForLocalStorage()),
    ).toBeNull()
  })
})

describe('closeFilter', () => {
  test('closes the sub header and schedules a scroll to the current segment', () => {
    jest.useFakeTimers()
    SegmentStore.getCurrentSegmentId.mockReturnValue(77)

    SegmentFilterUtils.closeFilter()

    expect(CatToolActions.closeSubHeader).toHaveBeenCalled()
    expect(SegmentFilterUtils.open).toBe(false)
    expect(SegmentFilterUtils.getStoredState().open).toBe(false)
    expect(SegmentActions.removeAllMutedSegments).toHaveBeenCalled()

    jest.advanceTimersByTime(600)

    expect(SegmentActions.scrollToSegment).toHaveBeenCalledWith(77)
  })
})

describe('goToNextRepetition', () => {
  test('moves to the next segment within the same repetition group', () => {
    SegmentStore.getCurrentSegment.mockReturnValue({segment_hash: 'hash-1'})
    SegmentStore.getCurrentSegmentId.mockReturnValue(1)
    SegmentFilterUtils.setStoredState({
      serverData: {grouping: {'hash-1': [1, 2, 3]}},
    })

    SegmentFilterUtils.goToNextRepetition('TRANSLATED')

    expect(segmentTranslation).toHaveBeenCalledWith(
      {segment_hash: 'hash-1'},
      'TRANSLATED',
      expect.any(Function),
    )
    expect(SegmentActions.openSegment).toHaveBeenCalledWith(2)
  })

  test('wraps around to the first segment of the group', () => {
    SegmentStore.getCurrentSegment.mockReturnValue({segment_hash: 'hash-1'})
    SegmentStore.getCurrentSegmentId.mockReturnValue(3)
    SegmentFilterUtils.setStoredState({
      serverData: {grouping: {'hash-1': [1, 2, 3]}},
    })

    SegmentFilterUtils.goToNextRepetition('TRANSLATED')

    expect(SegmentActions.openSegment).toHaveBeenCalledWith(1)
  })

  test('does nothing when there is no group for the current segment', () => {
    SegmentStore.getCurrentSegment.mockReturnValue({
      segment_hash: 'missing-hash',
    })
    SegmentStore.getCurrentSegmentId.mockReturnValue(1)
    SegmentFilterUtils.setStoredState({
      serverData: {grouping: {'hash-1': [1, 2, 3]}},
    })

    SegmentFilterUtils.goToNextRepetition('TRANSLATED')

    expect(segmentTranslation).not.toHaveBeenCalled()
  })
})

describe('goToNextRepetitionGroup', () => {
  test('moves to the first segment of the next repetition group', () => {
    SegmentStore.getCurrentSegment.mockReturnValue({segment_hash: 'hash-1'})
    SegmentFilterUtils.setStoredState({
      serverData: {grouping: {'hash-1': [1, 2], 'hash-2': [3, 4]}},
    })

    SegmentFilterUtils.goToNextRepetitionGroup('TRANSLATED')

    expect(SegmentActions.openSegment).toHaveBeenCalledWith(3)
  })

  test('wraps around to the first repetition group', () => {
    SegmentStore.getCurrentSegment.mockReturnValue({segment_hash: 'hash-2'})
    SegmentFilterUtils.setStoredState({
      serverData: {grouping: {'hash-1': [1, 2], 'hash-2': [3, 4]}},
    })

    SegmentFilterUtils.goToNextRepetitionGroup('TRANSLATED')

    expect(SegmentActions.openSegment).toHaveBeenCalledWith(1)
  })
})

describe('gotoPreviousSegment', () => {
  test('opens the previous segment in the filtered list', () => {
    SegmentFilterUtils.setStoredState({
      serverData: {segment_ids: ['1', '2', '3']},
    })
    SegmentStore.getCurrentSegmentId.mockReturnValue(2)

    SegmentFilterUtils.gotoPreviousSegment()

    expect(SegmentActions.openSegment).toHaveBeenCalledWith('1')
  })

  test('wraps around to the last segment when at the start of the list', () => {
    SegmentFilterUtils.setStoredState({
      serverData: {segment_ids: ['1', '2', '3']},
    })
    SegmentStore.getCurrentSegmentId.mockReturnValue(1)

    SegmentFilterUtils.gotoPreviousSegment()

    expect(SegmentActions.openSegment).toHaveBeenCalledWith('3')
  })

  test('does nothing when the list is empty', () => {
    SegmentFilterUtils.setStoredState({serverData: {segment_ids: []}})
    SegmentStore.getCurrentSegmentId.mockReturnValue(1)

    SegmentFilterUtils.gotoPreviousSegment()

    expect(SegmentActions.openSegment).not.toHaveBeenCalled()
  })
})

describe('gotoNextTranslatedSegment', () => {
  test('does nothing when there is no filtered data', () => {
    SegmentFilterUtils.setStoredState({serverData: {}})

    SegmentFilterUtils.gotoNextTranslatedSegment(1)

    expect(SegmentActions.openSegment).not.toHaveBeenCalled()
  })

  test('opens the next segment when it is already translated', () => {
    SegmentFilterUtils.setStoredState({serverData: {segment_ids: ['1', '2']}})
    SegmentStore.getSegmentByIdToJS.mockReturnValue({status: 'TRANSLATED'})

    SegmentFilterUtils.gotoNextTranslatedSegment(1)

    expect(SegmentActions.openSegment).toHaveBeenCalledWith('2')
  })

  test('skips draft/new segments until a translated one is found', () => {
    SegmentFilterUtils.setStoredState({
      serverData: {segment_ids: ['1', '2', '3']},
    })
    SegmentStore.getSegmentByIdToJS
      .mockReturnValueOnce({status: 'DRAFT'})
      .mockReturnValueOnce({status: 'TRANSLATED'})

    SegmentFilterUtils.gotoNextTranslatedSegment(1)

    expect(SegmentActions.openSegment).toHaveBeenCalledWith('3')
    expect(SegmentActions.openSegment).toHaveBeenCalledTimes(1)
  })

  test('stops when the next segment cannot be found', () => {
    SegmentFilterUtils.setStoredState({serverData: {segment_ids: ['1', '2']}})
    SegmentStore.getSegmentByIdToJS.mockReturnValue(undefined)

    SegmentFilterUtils.gotoNextTranslatedSegment(1)

    expect(SegmentActions.openSegment).not.toHaveBeenCalled()
  })
})

describe('gotoNextSegment', () => {
  test('opens the next segment in the filtered list', () => {
    SegmentFilterUtils.setStoredState({
      serverData: {segment_ids: ['1', '2', '3']},
    })

    SegmentFilterUtils.gotoNextSegment(1)

    expect(SegmentActions.openSegment).toHaveBeenCalledWith('2')
  })

  test('wraps around to the first segment when at the end of the list', () => {
    SegmentFilterUtils.setStoredState({
      serverData: {segment_ids: ['1', '2', '3']},
    })

    SegmentFilterUtils.gotoNextSegment(3)

    expect(SegmentActions.openSegment).toHaveBeenCalledWith('1')
  })

  test('does nothing when the list is empty', () => {
    SegmentFilterUtils.setStoredState({serverData: {segment_ids: []}})

    SegmentFilterUtils.gotoNextSegment(1)

    expect(SegmentActions.openSegment).not.toHaveBeenCalled()
  })
})
