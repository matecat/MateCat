jest.mock('../api/getLocalWarnings', () => ({
  getLocalWarnings: jest.fn(),
}))
jest.mock('../api/getGlossaryCheck', () => ({
  getGlossaryCheck: jest.fn(),
}))
jest.mock('../components/segments/utils/DraftMatecatUtils/tagUtils', () => ({
  removeTagsFromText: jest.fn((text) => text),
}))
jest.mock('../utils/commonUtils', () => ({
  dispatchCustomEvent: jest.fn(),
}))
jest.mock('./segmentDispatchActions', () => ({
  setSegmentWarnings: jest.fn(),
}))
jest.mock('../constants/CatToolConstants', () => ({
  UPDATE_TM_KEYS: 'UPDATE_TM_KEYS',
  HAVE_KEYS_GLOSSARY: 'HAVE_KEYS_GLOSSARY',
}))
jest.mock('../stores/CatToolStore', () => ({
  getJobTmKeys: jest.fn(),
  getHaveKeysGlossary: jest.fn(),
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))
jest.mock('../utils/offlineUtils', () => ({
  failedConnection: jest.fn(),
}))
jest.mock('../stores/SegmentStore', () => ({
  getCurrentSegment: jest.fn(),
}))

import {getSegmentsQa, startSegmentQACheck} from './segmentQaActions'
import {getLocalWarnings} from '../api/getLocalWarnings'
import {getGlossaryCheck} from '../api/getGlossaryCheck'
import CommonUtils from '../utils/commonUtils'
import {setSegmentWarnings} from './segmentDispatchActions'
import CatToolStore from '../stores/CatToolStore'
import OfflineUtils from '../utils/offlineUtils'
import SegmentStore from '../stores/SegmentStore'

describe('segmentQaActions', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    global.config = {
      ...global.config,
      password: 'pwd',
      segmentQACheckInterval: 1000,
    }
  })

  test('getSegmentsQa does nothing when segment is falsy', () => {
    getSegmentsQa(undefined)

    expect(getLocalWarnings).not.toHaveBeenCalled()
  })

  test('getSegmentsQa dispatches warnings from response details and glossary check when enabled', async () => {
    const segment = {
      sid: 1,
      original_sid: 1,
      status: 'DRAFT',
      translation: 'target text',
      updatedSource: 'source text',
      charactersCounter: 5,
    }
    getLocalWarnings.mockResolvedValueOnce({
      details: {
        id_segment: 1,
        issues_info: {tag: []},
        tag_mismatch: {},
      },
    })
    CatToolStore.getJobTmKeys.mockReturnValue([{key: 'k1'}])
    CatToolStore.getHaveKeysGlossary.mockReturnValue(true)
    getGlossaryCheck.mockResolvedValueOnce({})

    getSegmentsQa(segment)
    await Promise.resolve()
    await Promise.resolve()
    await Promise.resolve()

    expect(getLocalWarnings).toHaveBeenCalledWith({
      id: 1,
      id_job: 2,
      password: 'pwd',
      src_content: 'source text',
      trg_content: 'target text',
      segment_status: 'DRAFT',
      characters_counter: 5,
    })
    expect(setSegmentWarnings).toHaveBeenCalledWith(1, {tag: []}, {})
    expect(CommonUtils.dispatchCustomEvent).toHaveBeenCalledWith(
      'getWarning:local:success',
      expect.objectContaining({segment}),
    )
    expect(getGlossaryCheck).toHaveBeenCalledWith({
      idSegment: 1,
      target: 'target text',
      source: 'source text',
      keys: ['k1'],
    })
  })

  test('getSegmentsQa dispatches empty warnings when response has no details', async () => {
    const segment = {
      sid: 2,
      original_sid: 2,
      status: 'DRAFT',
      translation: 'x',
      updatedSource: 'y',
    }
    getLocalWarnings.mockResolvedValueOnce({})
    CatToolStore.getJobTmKeys.mockReturnValue(undefined)
    CatToolStore.getHaveKeysGlossary.mockReturnValue(false)

    getSegmentsQa(segment)
    await Promise.resolve()

    expect(setSegmentWarnings).toHaveBeenCalledWith(2, {}, {})
  })

  test('getSegmentsQa handles getLocalWarnings failure via OfflineUtils', async () => {
    const segment = {
      sid: 3,
      original_sid: 3,
      status: 'DRAFT',
      translation: 'x',
      updatedSource: 'y',
    }
    getLocalWarnings.mockRejectedValueOnce(new Error('fail'))
    CatToolStore.getJobTmKeys.mockReturnValue(undefined)
    CatToolStore.getHaveKeysGlossary.mockReturnValue(false)

    getSegmentsQa(segment)
    await Promise.resolve()
    await Promise.resolve()

    expect(OfflineUtils.failedConnection).toHaveBeenCalled()
  })

  test('getSegmentsQa waits for TM keys and glossary listeners before resolving', async () => {
    const segment = {
      sid: 4,
      original_sid: 4,
      status: 'DRAFT',
      translation: 'target',
      updatedSource: 'source',
    }
    getLocalWarnings.mockResolvedValueOnce({})
    CatToolStore.getJobTmKeys.mockReturnValue(undefined)
    CatToolStore.getHaveKeysGlossary.mockReturnValue(false)

    getSegmentsQa(segment)

    expect(CatToolStore.addListener).toHaveBeenCalledWith(
      'UPDATE_TM_KEYS',
      expect.any(Function),
    )
    expect(CatToolStore.addListener).toHaveBeenCalledWith(
      'HAVE_KEYS_GLOSSARY',
      expect.any(Function),
    )

    const setJobTmKeys = CatToolStore.addListener.mock.calls.find(
      ([type]) => type === 'UPDATE_TM_KEYS',
    )[1]
    const setHaveKeysGlossary = CatToolStore.addListener.mock.calls.find(
      ([type]) => type === 'HAVE_KEYS_GLOSSARY',
    )[1]

    setJobTmKeys()
    expect(CatToolStore.removeListener).toHaveBeenCalledWith(
      'UPDATE_TM_KEYS',
      setJobTmKeys,
    )

    setHaveKeysGlossary()
    expect(CatToolStore.removeListener).toHaveBeenCalledWith(
      'HAVE_KEYS_GLOSSARY',
      setHaveKeysGlossary,
    )

    await Promise.resolve()
  })

  test('startSegmentQACheck schedules getSegmentsQa with current segment', () => {
    jest.useFakeTimers()
    getLocalWarnings.mockResolvedValueOnce({})
    const currentSegment = {
      sid: 5,
      original_sid: 5,
      status: 'DRAFT',
      translation: 'x',
      updatedSource: 'y',
    }
    SegmentStore.getCurrentSegment.mockReturnValue(currentSegment)

    startSegmentQACheck()
    jest.advanceTimersByTime(1000)

    expect(getLocalWarnings).toHaveBeenCalledWith(
      expect.objectContaining({id: 5}),
    )

    jest.useRealTimers()
  })
})
