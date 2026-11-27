import {render} from '@testing-library/react'
import {HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE} from './components/modals/UnmodifiedFuzzyMatchModal'

jest.mock('./actions/SegmentActions', () => ({
  __esModule: true,
  default: {
    hideSegmentHeader: jest.fn(),
    modifiedTranslation: jest.fn(),
    addClassToSegment: jest.fn(),
    setSegmentSaving: jest.fn(),
    setChoosenSuggestion: jest.fn(),
    getSegmentVersionsIssues: jest.fn(),
    getTranslationMismatches: jest.fn(),
    setStatus: jest.fn(),
    setSavedTranslation: jest.fn(),
    removeClassToSegment: jest.fn(),
    propagateTranslation: jest.fn(),
    setSegmentPropagation: jest.fn(),
  },
}))

jest.mock('./utils/offlineUtils', () => ({
  __esModule: true,
  default: {
    offline: false,
    decrementOfflineCacheRemaining: jest.fn(),
    failedConnection: jest.fn(),
    changeStatusOffline: jest.fn(),
    checkConnection: jest.fn(),
    startOfflineMode: jest.fn(),
  },
}))

jest.mock('./api/setTranslation', () => ({
  setTranslation: jest.fn(),
}))

jest.mock('./actions/ModalsActions', () => ({
  __esModule: true,
  default: {
    showModalComponent: jest.fn(),
    onCloseModal: jest.fn(),
  },
}))

jest.mock('./actions/CatToolActions', () => ({
  __esModule: true,
  default: {
    reloadQualityReport: jest.fn(),
    checkWarnings: jest.fn(),
    setProgress: jest.fn(),
    removeAllNotifications: jest.fn(),
    addNotification: jest.fn(),
    processErrors: jest.fn(),
  },
}))

jest.mock('./utils/commonUtils', () => ({
  __esModule: true,
  default: {
    dispatchCustomEvent: jest.fn(),
  },
}))

jest.mock('./utils/segmentUtils', () => ({
  __esModule: true,
  default: {
    createSetTranslationRequest: jest.fn(() => ({fakeRequest: true})),
  },
}))

jest.mock('./stores/SegmentStore', () => ({
  __esModule: true,
  default: {
    setLastTranslatedSegmentId: jest.fn(),
  },
}))

const buildSegment = (overrides = {}) => ({
  sid: 1,
  propagable: false,
  status: 'DRAFT',
  modified: false,
  translation: 'hello',
  ...overrides,
})

describe('setTranslationUtil', () => {
  let SetTranslationUtil
  let SegmentActions
  let OfflineUtils
  let setTranslationApi
  let ModalsActions
  let CatToolActions
  let CommonUtils

  beforeEach(() => {
    jest.resetModules()
    localStorage.clear()
    global.config = {
      id_job: 2, // must match id_job/userMail from setupFiles.jest.js's default
      userMail: 'jest-user@example.com', // config so HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE
      // (imported once, statically, above) matches what this fresh require() recomputes
      isReview: false,
      offlineModeEnabled: true,
      alternativesEnabled: false,
      status_labels: {NEW: 'NEW', DRAFT: 'DRAFT'},
    }

    SetTranslationUtil = require('./setTranslationUtil')
    SegmentActions = require('./actions/SegmentActions').default
    OfflineUtils = require('./utils/offlineUtils').default
    setTranslationApi = require('./api/setTranslation').setTranslation
    ModalsActions = require('./actions/ModalsActions').default
    CatToolActions = require('./actions/CatToolActions').default
    CommonUtils = require('./utils/commonUtils').default

    // Safe default: a pending promise so any test that reaches the network call
    // without caring about its resolution doesn't blow up on `undefined.then`.
    setTranslationApi.mockImplementation(() => new Promise(() => {}))
  })

  describe('segmentTranslation', () => {
    test('returns early when segment is falsy', () => {
      expect(
        SetTranslationUtil.segmentTranslation(null, 'DRAFT', jest.fn()),
      ).toBeUndefined()
      expect(SegmentActions.hideSegmentHeader).not.toHaveBeenCalled()
    })

    test('proceeds directly (no modal) for an unmodified draft segment', () => {
      const segment = buildSegment({status: 'DRAFT', modified: false})
      const callback = jest.fn()

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', callback, false)

      expect(SegmentActions.hideSegmentHeader).toHaveBeenCalledWith(1)
      // status is DRAFT so the modified class should be kept (not cleared)
      expect(SegmentActions.modifiedTranslation).not.toHaveBeenCalled()
      expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
    })

    test('clears modified class when confirming status is not draft/new', () => {
      const segment = buildSegment({status: 'TRANSLATED', modified: false})

      SetTranslationUtil.segmentTranslation(
        segment,
        'APPROVED2',
        jest.fn(),
        false,
      )

      expect(SegmentActions.modifiedTranslation).toHaveBeenCalledWith(1, false)
    })

    test('shows the unmodified-fuzzy-match modal and proceeds on confirmation', () => {
      const segment = buildSegment({
        status: 'DRAFT',
        modified: false,
        translation: 'fuzzy text',
        contributions: {matches: [{match: '85%', translation: 'fuzzy text'}]},
        choosenSuggestionIndex: 0,
      })

      SetTranslationUtil.segmentTranslation(
        segment,
        'TRANSLATED',
        jest.fn(),
        false,
      )

      expect(ModalsActions.showModalComponent).toHaveBeenCalledTimes(1)
      const [, props, title] = ModalsActions.showModalComponent.mock.calls[0]
      expect(title).toBe('Confirm unedited fuzzy match?')
      expect(SegmentActions.hideSegmentHeader).not.toHaveBeenCalled()

      // Simulate the user confirming through the modal.
      props.successCallback()

      expect(SegmentActions.hideSegmentHeader).toHaveBeenCalledWith(1)
    })

    test('skips the fuzzy-match warning when the dismissal flag is stored', () => {
      localStorage.setItem(HIDE_UNMODIFIED_FUZZY_MATCH_MODAL_STORAGE, '1')
      const segment = buildSegment({
        status: 'DRAFT',
        modified: false,
        translation: 'fuzzy text',
        contributions: {matches: [{match: '85%', translation: 'fuzzy text'}]},
        choosenSuggestionIndex: 0,
      })

      SetTranslationUtil.segmentTranslation(
        segment,
        'TRANSLATED',
        jest.fn(),
        false,
      )

      expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
      expect(SegmentActions.hideSegmentHeader).toHaveBeenCalledWith(1)
    })

    test('does not warn about fuzzy match when the suggestion was edited', () => {
      const segment = buildSegment({
        status: 'DRAFT',
        modified: false,
        translation: 'edited text',
        contributions: {matches: [{match: '85%', translation: 'fuzzy text'}]},
        choosenSuggestionIndex: 0,
      })

      SetTranslationUtil.segmentTranslation(
        segment,
        'TRANSLATED',
        jest.fn(),
        false,
      )

      expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
      expect(SegmentActions.hideSegmentHeader).toHaveBeenCalledWith(1)
    })

    test('shows propagation confirmation modal, "only this segment" branch keeps propagate false', () => {
      const segment = buildSegment({
        propagable: true,
        status: 'translated',
        modified: true,
      })

      SetTranslationUtil.segmentTranslation(segment, 'APPROVED', jest.fn())

      expect(ModalsActions.showModalComponent).toHaveBeenCalledTimes(1)
      const [component, props, title] =
        ModalsActions.showModalComponent.mock.calls[0]
      expect(title).toBe('Confirmation required ')
      expect(props.successText).toBe('Only this segment')
      expect(props.cancelText).toBe('Propagate to All')
      expect(component).toBeDefined()

      props.successCallback()

      expect(SegmentActions.hideSegmentHeader).toHaveBeenCalledWith(1)
      expect(ModalsActions.onCloseModal).toHaveBeenCalled()
    })

    test('shows propagation confirmation modal, "propagate to all" branch clears modal', () => {
      const segment = buildSegment({
        propagable: true,
        status: 'translated',
        modified: true,
      })

      SetTranslationUtil.segmentTranslation(segment, 'APPROVED', jest.fn())

      const [, props] = ModalsActions.showModalComponent.mock.calls[0]
      props.cancelCallback()

      expect(SegmentActions.hideSegmentHeader).toHaveBeenCalledWith(1)
      expect(ModalsActions.onCloseModal).toHaveBeenCalled()
    })
  })

  describe('setTranslationTail offline branch (via segmentTranslation)', () => {
    test('queues the translation and defers to offline handling while offline', () => {
      OfflineUtils.offline = true
      const callback = jest.fn()
      const segment = buildSegment()

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', callback, false)

      expect(SegmentActions.setSegmentSaving).toHaveBeenCalledWith(1, true)
      expect(OfflineUtils.decrementOfflineCacheRemaining).toHaveBeenCalled()
      expect(OfflineUtils.failedConnection).toHaveBeenCalled()
      expect(OfflineUtils.changeStatusOffline).toHaveBeenCalledWith(1)
      expect(OfflineUtils.checkConnection).toHaveBeenCalled()
      expect(callback).toHaveBeenCalled()
      expect(setTranslationApi).not.toHaveBeenCalled()
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(false)
    })

    test('a segment saved again while queued replaces its queued save and takes one cache slot', () => {
      OfflineUtils.offline = true
      const SegmentUtils = require('./utils/segmentUtils').default
      SegmentUtils.createSetTranslationRequest.mockImplementation(
        (segment, status) => ({translation: segment.translation, status}),
      )

      SetTranslationUtil.segmentTranslation(
        buildSegment({translation: 'first'}),
        'DRAFT',
        jest.fn(),
        false,
      )
      SetTranslationUtil.segmentTranslation(
        buildSegment({translation: 'second'}),
        'TRANSLATED',
        jest.fn(),
        false,
      )
      expect(OfflineUtils.decrementOfflineCacheRemaining).toHaveBeenCalledTimes(
        1,
      )

      OfflineUtils.offline = false
      SetTranslationUtil.execSetTranslationTail()

      expect(setTranslationApi).toHaveBeenCalledTimes(1)
      expect(setTranslationApi).toHaveBeenCalledWith({
        translation: 'second',
        status: 'TRANSLATED',
      })
    })

    test('a save queued offline does not move the translator again when it is sent', () => {
      OfflineUtils.offline = true
      const callback = jest.fn()

      SetTranslationUtil.segmentTranslation(
        buildSegment(),
        'TRANSLATED',
        callback,
        false,
      )
      expect(callback).toHaveBeenCalledTimes(1)

      OfflineUtils.offline = false
      SetTranslationUtil.execSetTranslationTail()

      expect(setTranslationApi).toHaveBeenCalledTimes(1)
      expect(callback).toHaveBeenCalledTimes(1)
    })
  })

  describe('sending the queue', () => {
    const flush = () => new Promise((resolve) => setTimeout(resolve, 0))
    const deferred = () => {
      let resolve, reject
      const promise = new Promise((res, rej) => {
        resolve = res
        reject = rej
      })
      return {promise, resolve, reject}
    }
    const save = (overrides, status = 'TRANSLATED', errorCallback) =>
      SetTranslationUtil.segmentTranslation(
        buildSegment(overrides),
        status,
        jest.fn(),
        false,
        errorCallback,
      )

    let SegmentUtils
    beforeEach(() => {
      OfflineUtils.offline = false
      SegmentUtils = require('./utils/segmentUtils').default
      SegmentUtils.createSetTranslationRequest.mockImplementation(
        (segment, status) => ({
          sid: segment.sid,
          translation: segment.translation,
          status,
        }),
      )
    })

    test('sends one save at a time, in the order they were made', async () => {
      const first = deferred()
      setTranslationApi
        .mockReturnValueOnce(first.promise)
        .mockResolvedValue({data: 'OK'})

      save({sid: 1, translation: 'one'}, 'DRAFT')
      save({sid: 1, translation: 'one, edited'}, 'TRANSLATED')
      save({sid: 2, translation: 'two'})

      expect(setTranslationApi).toHaveBeenCalledTimes(1)

      first.resolve({data: 'OK'})
      await flush()

      expect(setTranslationApi.mock.calls.map(([request]) => request)).toEqual([
        {sid: 1, translation: 'one', status: 'DRAFT'},
        {sid: 1, translation: 'one, edited', status: 'TRANSLATED'},
        {sid: 2, translation: 'two', status: 'TRANSLATED'},
      ])
    })

    test('builds the request when the save is made, not when it is sent', () => {
      setTranslationApi.mockReturnValue(new Promise(() => {}))

      save({sid: 1})
      save({sid: 2})

      expect(setTranslationApi).toHaveBeenCalledTimes(1)
      expect(SegmentUtils.createSetTranslationRequest).toHaveBeenCalledTimes(2)
    })

    test('counts a save on its way as not saved yet', async () => {
      const pending = deferred()
      setTranslationApi.mockReturnValue(pending.promise)

      save({sid: 1})
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(false)

      pending.resolve({data: 'OK'})
      await flush()
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(true)
    })

    test.each([
      ['an answer without OK', () => Promise.resolve({data: 'KO'})],
      ['a server failure', () => Promise.reject({response: {status: 500}})],
    ])(
      'reports %s once, without retrying it, and sends the next save',
      async (_, answer) => {
        setTranslationApi
          .mockImplementationOnce(answer)
          .mockResolvedValue({data: 'OK'})
        const errorCallback = jest.fn()

        save({sid: 1}, 'TRANSLATED', errorCallback)
        save({sid: 2})
        await flush()

        expect(CatToolActions.processErrors).toHaveBeenCalledTimes(1)
        expect(errorCallback).toHaveBeenCalledTimes(1)
        expect(SegmentActions.setStatus).not.toHaveBeenCalledWith(
          1,
          null,
          'TRANSLATED',
        )
        expect(OfflineUtils.startOfflineMode).not.toHaveBeenCalled()
        expect(setTranslationApi).toHaveBeenCalledTimes(2)
        expect(SegmentActions.setSegmentSaving).toHaveBeenCalledWith(1, false)
        expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(true)
      },
    )

    test('reports a refusal with the server errors and sends the next save', async () => {
      const errors = [{code: -5, message: 'disabled'}]
      setTranslationApi
        .mockRejectedValueOnce({response: {status: 200}, errors})
        .mockResolvedValue({data: 'OK'})
      const errorCallback = jest.fn()

      save({sid: 1}, 'TRANSLATED', errorCallback)
      save({sid: 2})
      await flush()

      expect(CatToolActions.processErrors).toHaveBeenCalledWith(
        errors,
        'setTranslation',
      )
      expect(errorCallback).toHaveBeenCalledWith(errors)
      expect(setTranslationApi).toHaveBeenCalledTimes(2)
    })

    test('a throw while updating the page after a save neither stops the queue nor sends the save again', async () => {
      setTranslationApi.mockResolvedValue({data: 'OK'})
      SegmentActions.setStatus.mockImplementationOnce(() => {
        throw new Error('render failed')
      })
      // Queued offline, then sent here, so the test holds the promise the throw rejects
      OfflineUtils.offline = true
      save({sid: 1})
      save({sid: 2})
      OfflineUtils.offline = false

      await expect(SetTranslationUtil.execSetTranslationTail()).rejects.toThrow(
        'render failed',
      )
      await flush()

      expect(setTranslationApi).toHaveBeenCalledTimes(2)
      expect(OfflineUtils.startOfflineMode).not.toHaveBeenCalled()
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(true)
    })

    test('a throw in the caller error handling does not stop the queue', async () => {
      setTranslationApi
        .mockRejectedValueOnce({response: {status: 500}})
        .mockResolvedValue({data: 'OK'})

      // Queued offline, then sent here, so the test holds the promise the throw rejects
      OfflineUtils.offline = true
      save({sid: 1}, 'TRANSLATED', () => {
        throw new Error('restore failed')
      })
      save({sid: 2})
      OfflineUtils.offline = false

      await expect(SetTranslationUtil.execSetTranslationTail()).rejects.toThrow(
        'restore failed',
      )
      await flush()

      expect(setTranslationApi).toHaveBeenCalledTimes(2)
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(true)
    })

    test('a throw in the navigation callback does not keep the save from being sent', () => {
      setTranslationApi.mockReturnValue(new Promise(() => {}))

      expect(() =>
        SetTranslationUtil.segmentTranslation(
          buildSegment(),
          'TRANSLATED',
          () => {
            throw new Error('open failed')
          },
          false,
        ),
      ).toThrow('open failed')
      expect(setTranslationApi).toHaveBeenCalledTimes(1)
    })

    test('a segment saved again goes after the saves made before it', async () => {
      const first = deferred()
      setTranslationApi
        .mockReturnValueOnce(first.promise)
        .mockResolvedValue({data: 'OK'})

      save({sid: 9, translation: 'sent'})
      save({sid: 1, translation: 'one'})
      save({sid: 2, translation: 'two'})
      save({sid: 1, translation: 'one, edited'})
      first.resolve({data: 'OK'})
      await flush()

      expect(
        setTranslationApi.mock.calls.map(([request]) => request.translation),
      ).toEqual(['sent', 'two', 'one, edited'])
    })

    test('a segment keeps showing as saving while a newer save of it waits', async () => {
      const first = deferred()
      const second = deferred()
      setTranslationApi
        .mockReturnValueOnce(first.promise)
        .mockReturnValueOnce(second.promise)

      save({sid: 1, translation: 'one'})
      save({sid: 1, translation: 'one, edited'})
      first.resolve({data: 'OK'})
      await flush()
      expect(SegmentActions.setSegmentSaving).not.toHaveBeenCalledWith(1, false)

      second.resolve({data: 'OK'})
      await flush()
      expect(SegmentActions.setSegmentSaving).toHaveBeenLastCalledWith(1, false)
    })

    describe('a lost connection', () => {
      const lost = () => Promise.reject(new TypeError('Failed to fetch'))
      const settle = () => jest.advanceTimersByTimeAsync(0)

      beforeEach(() => {
        jest.useFakeTimers()
        global.config.offlineModeEnabled = true
      })
      afterEach(() => {
        jest.useRealTimers()
      })

      test('sends the save again, ahead of the later saves, while the server is reachable', async () => {
        setTranslationApi
          .mockImplementationOnce(lost)
          .mockResolvedValue({data: 'OK'})

        save({sid: 1})
        save({sid: 2})
        await settle()
        expect(OfflineUtils.startOfflineMode).toHaveBeenCalledTimes(1)
        expect(SegmentActions.setSegmentSaving).not.toHaveBeenCalledWith(
          1,
          false,
        )

        await jest.advanceTimersByTimeAsync(2000)

        expect(
          setTranslationApi.mock.calls.map(([request]) => request.sid),
        ).toEqual([1, 1, 2])
        expect(CatToolActions.processErrors).not.toHaveBeenCalled()
        expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(true)
      })

      test('waits for the connection to come back while offline', async () => {
        setTranslationApi.mockImplementationOnce(lost)
        OfflineUtils.startOfflineMode.mockImplementation(() => {
          OfflineUtils.offline = true
        })

        save({sid: 1})
        await settle()
        await jest.advanceTimersByTimeAsync(10000)

        expect(setTranslationApi).toHaveBeenCalledTimes(1)
        expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(false)
      })

      test('reports the save as not saved after the third attempt and sends the next one', async () => {
        setTranslationApi
          .mockImplementationOnce(lost)
          .mockImplementationOnce(lost)
          .mockImplementationOnce(lost)
          .mockResolvedValue({data: 'OK'})
        const errorCallback = jest.fn()

        save({sid: 1}, 'TRANSLATED', errorCallback)
        save({sid: 2})
        await jest.advanceTimersByTimeAsync(10000)

        expect(
          setTranslationApi.mock.calls.map(([request]) => request.sid),
        ).toEqual([1, 1, 1, 2])
        expect(CatToolActions.processErrors).toHaveBeenCalledTimes(1)
        expect(errorCallback).toHaveBeenCalledTimes(1)
        expect(SegmentActions.setSegmentSaving).toHaveBeenCalledWith(1, false)
        expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(true)
      })

      test('drops the save when the segment was saved again meanwhile', async () => {
        const first = deferred()
        setTranslationApi
          .mockReturnValueOnce(first.promise)
          .mockResolvedValue({data: 'OK'})

        save({sid: 1, translation: 'old'})
        save({sid: 1, translation: 'new'})
        first.reject(new TypeError('Failed to fetch'))
        await jest.advanceTimersByTimeAsync(2000)

        expect(
          setTranslationApi.mock.calls.slice(1).map(([request]) => request),
        ).toEqual([{sid: 1, translation: 'new', status: 'TRANSLATED'}])
      })
    })
  })

  describe('empty target', () => {
    test.each([
      ['online', false],
      ['offline', true],
    ])('is refused %s without being marked as saving', (_, offline) => {
      OfflineUtils.offline = offline
      const callback = jest.fn()
      const errorCallback = jest.fn()

      SetTranslationUtil.segmentTranslation(
        buildSegment({translation: ''}),
        'TRANSLATED',
        callback,
        false,
        errorCallback,
      )

      expect(SegmentActions.setSegmentSaving).not.toHaveBeenCalled()
      expect(setTranslationApi).not.toHaveBeenCalled()
      expect(OfflineUtils.decrementOfflineCacheRemaining).not.toHaveBeenCalled()
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(true)
      expect(callback).not.toHaveBeenCalled()
      expect(errorCallback).toHaveBeenCalledTimes(1)
    })

    test('a target made only of tags is saved', () => {
      SetTranslationUtil.segmentTranslation(
        buildSegment({translation: '&lt;g id="1"&gt;&lt;/g&gt;'}),
        'TRANSLATED',
        jest.fn(),
        false,
      )

      expect(setTranslationApi).toHaveBeenCalledTimes(1)
    })
  })

  describe('execSetTranslationTail online branch (via segmentTranslation)', () => {
    test('returns immediately when the queue is empty', () => {
      expect(SetTranslationUtil.execSetTranslationTail()).toBeUndefined()
      expect(setTranslationApi).not.toHaveBeenCalled()
    })

    test('moves on to the next segment while the save is still in flight', () => {
      setTranslationApi.mockReturnValue(new Promise(() => {}))
      const segment = buildSegment()
      const callback = jest.fn()

      SetTranslationUtil.segmentTranslation(
        segment,
        'TRANSLATED',
        callback,
        false,
      )

      expect(setTranslationApi).toHaveBeenCalledTimes(1)
      expect(callback).toHaveBeenCalledTimes(1)
    })

    test('processes the online success path, sets status/progress, and empties the tail', async () => {
      setTranslationApi.mockResolvedValue({data: 'OK', propagation: null})
      const segment = buildSegment()

      const callback = jest.fn()

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', callback, false)
      // The callback runs as the request leaves, without waiting for the response.
      expect(callback).toHaveBeenCalledTimes(1)
      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(callback).toHaveBeenCalled()
      expect(setTranslationApi).toHaveBeenCalledWith({fakeRequest: true})
      expect(SegmentActions.setChoosenSuggestion).toHaveBeenCalledWith(1, null)
      expect(SegmentActions.setSegmentSaving).toHaveBeenCalledWith(1, false)
      expect(SegmentActions.setStatus).toHaveBeenCalledWith(1, null, 'DRAFT')
      // What the server now holds, shown again if the target is later left empty
      expect(SegmentActions.setSavedTranslation).toHaveBeenCalledWith(
        1,
        'hello',
      )
      expect(CatToolActions.setProgress).toHaveBeenCalledWith({
        data: 'OK',
        propagation: null,
      })
      expect(CatToolActions.checkWarnings).toHaveBeenCalledWith(false)
      expect(CommonUtils.dispatchCustomEvent).toHaveBeenCalledWith(
        'setTranslation:success',
        {segment},
      )
      // no propagation data -> falls into the "else" branch of checkSegmentsPropagation
      expect(SegmentActions.setSegmentPropagation).toHaveBeenCalledWith(
        1,
        null,
        false,
      )
      expect(CatToolActions.reloadQualityReport).not.toHaveBeenCalled()
      expect(SegmentActions.getTranslationMismatches).not.toHaveBeenCalled()
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(true)
    })

    test('does not show the segment as saved when the response data is not OK', async () => {
      setTranslationApi.mockResolvedValue({data: 'KO'})
      const segment = buildSegment()

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', jest.fn(), false)
      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(SegmentActions.setStatus).not.toHaveBeenCalled()
      expect(CatToolActions.setProgress).not.toHaveBeenCalled()
      expect(SegmentActions.setSegmentPropagation).not.toHaveBeenCalled()
    })

    test('triggers review-specific and alternatives side effects when enabled', async () => {
      global.config.isReview = true
      global.config.alternativesEnabled = true
      setTranslationApi.mockResolvedValue({data: 'OK', propagation: null})
      const segment = buildSegment()

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', jest.fn(), false)
      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(SegmentActions.getSegmentVersionsIssues).toHaveBeenCalledWith(1)
      expect(CatToolActions.reloadQualityReport).toHaveBeenCalled()
      expect(SegmentActions.getTranslationMismatches).toHaveBeenCalledWith(1)
    })

    test('propagates translation and notifies about excluded locked-segment repetitions', async () => {
      setTranslationApi.mockResolvedValue({
        data: 'OK',
        propagation: {
          propagated_ids: [2, 3],
          segments_for_propagation: {
            not_propagated: {ice: {id: [4]}, not_ice: {id: []}},
          },
        },
      })
      // status "translated" + modified -> goes through the confirmation modal, which is the
      // only path that can produce autoPropagate:false (needed for the notification branch).
      const segment = buildSegment({
        propagable: true,
        status: 'translated',
        modified: true,
      })

      SetTranslationUtil.segmentTranslation(segment, 'APPROVED', jest.fn())
      const [, props] = ModalsActions.showModalComponent.mock.calls[0]
      props.cancelCallback() // "Propagate to All" -> propagate:true, autoPropagate:false

      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(SegmentActions.propagateTranslation).toHaveBeenCalledWith(
        1,
        [2, 3],
        'APPROVED',
      )
      expect(CatToolActions.removeAllNotifications).toHaveBeenCalled()
      expect(CatToolActions.addNotification).toHaveBeenCalledWith(
        expect.objectContaining({title: 'Segment propagated', type: 'info'}),
      )
      const {container} = render(
        CatToolActions.addNotification.mock.calls[0][0].text,
      )
      expect(container).toHaveTextContent(/locked segments have been excluded/)
      expect(container).not.toHaveTextContent(
        /non-locked segments have been excluded/,
      )
    })

    test('notifies with the non-ice-locked message when non-ice repetitions are excluded', async () => {
      setTranslationApi.mockResolvedValue({
        data: 'OK',
        propagation: {
          propagated_ids: [2],
          segments_for_propagation: {
            not_propagated: {ice: {id: []}, not_ice: {id: [5]}},
          },
        },
      })
      const segment = buildSegment({
        propagable: true,
        status: 'translated',
        modified: true,
      })

      SetTranslationUtil.segmentTranslation(segment, 'APPROVED', jest.fn())
      const [, props] = ModalsActions.showModalComponent.mock.calls[0]
      props.cancelCallback()

      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(CatToolActions.addNotification).toHaveBeenCalled()
      const {container} = render(
        CatToolActions.addNotification.mock.calls[0][0].text,
      )
      expect(container).toHaveTextContent(
        /non-locked segments have been excluded/,
      )
    })

    test('does not notify when autoPropagate is true even though propagated', async () => {
      setTranslationApi.mockResolvedValue({
        data: 'OK',
        propagation: {
          propagated_ids: [2],
          segments_for_propagation: {not_propagated: {}},
        },
      })
      // status/modified combo takes the direct (no-modal) path, which always sets autoPropagate:true
      const segment = buildSegment({
        propagable: true,
        status: 'draft',
        modified: false,
      })

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', jest.fn())
      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
      expect(SegmentActions.propagateTranslation).toHaveBeenCalledWith(
        1,
        [2],
        'DRAFT',
      )
      expect(CatToolActions.addNotification).not.toHaveBeenCalled()
    })

    test('re-queues the item and switches to offline mode on a network error', async () => {
      // What fetch rejects with when the connection is lost
      setTranslationApi.mockRejectedValue(new TypeError('Failed to fetch'))
      const segment = buildSegment()

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', jest.fn(), false)
      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(OfflineUtils.changeStatusOffline).toHaveBeenCalledWith(1)
      expect(OfflineUtils.startOfflineMode).toHaveBeenCalled()
      // Still saving: it is sent again, and reported once the attempts run out
      expect(SegmentActions.setSegmentSaving).not.toHaveBeenCalledWith(1, false)
      expect(CatToolActions.processErrors).not.toHaveBeenCalled()
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(false)
    })

    test('reports errors through CatToolActions and does not re-queue when errors are present', async () => {
      setTranslationApi.mockRejectedValue({errors: [{message: 'boom'}]})
      const segment = buildSegment()

      SetTranslationUtil.segmentTranslation(segment, 'DRAFT', jest.fn(), false)
      await Promise.resolve()
      await Promise.resolve()
      await Promise.resolve()

      expect(CatToolActions.processErrors).toHaveBeenCalledWith(
        [{message: 'boom'}],
        'setTranslation',
      )
      expect(OfflineUtils.startOfflineMode).not.toHaveBeenCalled()
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(true)
    })
  })

  describe('translationIsToSaveBeforeClose', () => {
    test('is true for a modified, non-empty, new/draft segment not already queued', () => {
      const segment = buildSegment({
        status: 'NEW',
        modified: true,
        translation: 'x',
      })
      expect(SetTranslationUtil.translationIsToSaveBeforeClose(segment)).toBe(
        true,
      )
    })

    test('is false when the translation is empty', () => {
      const segment = buildSegment({
        status: 'NEW',
        modified: true,
        translation: '',
      })
      expect(SetTranslationUtil.translationIsToSaveBeforeClose(segment)).toBe(
        false,
      )
    })

    test('is false when the segment is already waiting in the queue', () => {
      OfflineUtils.offline = true
      SetTranslationUtil.segmentTranslation(
        buildSegment({translation: 'queued'}),
        'DRAFT',
        jest.fn(),
        false,
      )
      const segment = buildSegment({
        status: 'DRAFT',
        modified: true,
        translation: 'edited after',
      })
      expect(SetTranslationUtil.translationIsToSaveBeforeClose(segment)).toBe(
        false,
      )
    })

    test('is false when the segment was not modified', () => {
      const segment = buildSegment({
        status: 'NEW',
        modified: false,
        translation: 'x',
      })
      expect(SetTranslationUtil.translationIsToSaveBeforeClose(segment)).toBe(
        false,
      )
    })

    test('is false when the status is neither new nor draft', () => {
      const segment = buildSegment({
        status: 'TRANSLATED',
        modified: true,
        translation: 'x',
      })
      expect(SetTranslationUtil.translationIsToSaveBeforeClose(segment)).toBe(
        false,
      )
    })
  })

  describe('isTranslationTailEmpty', () => {
    test('is true when nothing has been queued', () => {
      expect(SetTranslationUtil.isTranslationTailEmpty()).toBe(true)
    })
  })
})
